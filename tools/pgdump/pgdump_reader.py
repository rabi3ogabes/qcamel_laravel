"""Minimal reader for PostgreSQL custom-format dumps (pg_dump -Fc, archive 1.14-1.16).

Reads the table of contents and the COPY data blocks (none / gzip / zstd compressed)
without pg_restore. Treats the dump strictly as data.

usage:
  python -I pgdump_reader.py toc  <dump>
  python -I pgdump_reader.py copy <dump> <schema> <table>     -> COPY text to stdout
"""
import io
import struct
import sys
import zlib

try:
    from compression import zstd
except ImportError:  # pragma: no cover
    zstd = None


class Reader:
    def __init__(self, f):
        self.f = f

    def byte(self):
        b = self.f.read(1)
        if not b:
            raise EOFError
        return b[0]

    def int(self):
        sign = self.byte()
        raw = self.f.read(self.int_size)
        v = int.from_bytes(raw, 'little')
        return -v if sign else v

    def str(self):
        n = self.int()
        if n < 0:
            return None
        return self.f.read(n).decode('utf-8', 'replace')

    def offset(self):
        flag = self.byte()
        raw = self.f.read(self.off_size)
        return flag, int.from_bytes(raw, 'little')


def read_toc(path):
    f = open(path, 'rb')
    r = Reader(f)
    assert f.read(5) == b'PGDMP', 'not a custom-format dump'
    vmaj, vmin, vrev = r.byte(), r.byte(), r.byte()
    version = (vmaj, vmin, vrev)
    r.int_size, r.off_size = r.byte(), r.byte()
    fmt = r.byte()
    assert fmt == 1, f'unsupported archive format {fmt}'
    if version >= (1, 15, 0):
        compression = r.byte()  # 0 none, 1 gzip, 2 lz4, 3 zstd
    else:
        level = r.int()
        compression = 1 if level != 0 else 0
    ts = [r.int() for _ in range(7)]
    dbname = r.str()
    remote_ver = r.str()
    dump_ver = r.str()
    n = r.int()
    entries = []
    for _ in range(n):
        e = {}
        e['dump_id'] = r.int()
        e['had_dumper'] = r.int()
        e['table_oid'] = r.str()
        e['oid'] = r.str()
        e['tag'] = r.str()
        e['desc'] = r.str()
        e['section'] = r.int()
        e['defn'] = r.str()
        e['drop'] = r.str()
        e['copy'] = r.str()
        e['namespace'] = r.str()
        e['tablespace'] = r.str()
        if version >= (1, 14, 0):
            e['tableam'] = r.str()
        if version >= (1, 16, 0):
            e['relkind'] = r.int()
        e['owner'] = r.str()
        _with_oids = r.str()
        deps = []
        while True:
            d = r.str()
            if d is None:
                break
            deps.append(d)
        e['deps'] = deps
        e['data_flag'], e['data_offset'] = r.offset()
        entries.append(e)
    meta = {'version': version, 'compression': compression, 'dbname': dbname, 'server': remote_ver, 'pg_dump': dump_ver, 'data_start': f.tell()}
    return f, r, meta, entries


def iter_blocks(f, r, start):
    """Yield (dump_id, raw_bytes) for every data block, scanning sequentially."""
    f.seek(start)
    while True:
        try:
            block_type = r.byte()
        except EOFError:
            return
        dump_id = r.int()
        chunks = []
        while True:
            n = r.int()
            if n == 0:
                break
            chunks.append(f.read(n))
        yield block_type, dump_id, b''.join(chunks)


def decompress(raw, compression):
    if compression == 0:
        return raw
    if compression == 1:
        return zlib.decompress(raw)
    if compression == 3:
        return zstd.decompress(raw)
    raise RuntimeError(f'compression {compression} not supported')


def table_data(path, schema, table):
    f, r, meta, entries = read_toc(path)
    target = [e for e in entries if e['desc'] == 'TABLE DATA' and e['namespace'] == schema and e['tag'] == table]
    if not target:
        return None, None
    e = target[0]
    if e['data_flag'] == 2:  # K_OFFSET_POS_SET: seek straight to the block
        f.seek(e['data_offset'])
        _bt = r.byte()
        _id = r.int()
        chunks = []
        while True:
            n = r.int()
            if n == 0:
                break
            chunks.append(f.read(n))
        return e['copy'], decompress(b''.join(chunks), meta['compression']).decode('utf-8')
    for _bt, dump_id, raw in iter_blocks(f, r, meta['data_start']):
        if dump_id == e['dump_id']:
            return e['copy'], decompress(raw, meta['compression']).decode('utf-8')
    return e['copy'], ''


if __name__ == '__main__':
    cmd, path = sys.argv[1], sys.argv[2]
    if cmd == 'toc':
        f, r, meta, entries = read_toc(path)
        print({k: v for k, v in meta.items() if k != 'data_start'})
        for e in entries:
            if e['desc'] in ('TABLE', 'TABLE DATA'):
                print(f"{e['desc']:<11} {e['namespace']}.{e['tag']}  flag={e['data_flag']}")
    elif cmd == 'copy':
        stmt, data = table_data(path, sys.argv[3], sys.argv[4])
        sys.stdout.write((stmt or '') + '\n' + (data or ''))
