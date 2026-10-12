"""Convert the legacy qcamelmc tables of a pg_dump custom archive into SQLite.

usage: python -I dump_to_sqlite.py <dump> <out.sqlite>
Tables: public.* legacy tables + auth.users (as "auth_users"). Booleans t/f -> 1/0,
COPY escapes decoded, NULL (\\N) -> NULL. Columns are TEXT (the importer casts).
"""
import os
import re
import sqlite3
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from pgdump_reader import decompress, read_toc  # noqa: E402

PUBLIC = ['settings', 'events', 'tickets', 'pos_users', 'customers', 'orders', 'ticket_holders', 'deleted_tickets',
          'expired_qr_codes', 'payment_events', 'payment_errors', 'email_delivery_events', 'staff_attendance',
          'pos_receipts', 'popup_banners', 'page_views', 'activity_logs', 'login_attempts', 'admin_users', 'user_roles']
WANTED = {('public', t): t for t in PUBLIC} | {('auth', 'users'): 'auth_users'}

ESC = {'b': '\b', 'f': '\f', 'n': '\n', 'r': '\r', 't': '\t', 'v': '\v', '\\': '\\'}


def unescape(field):
    if field == r'\N':
        return None
    if '\\' not in field:
        return field
    out, i = [], 0
    while i < len(field):
        c = field[i]
        if c != '\\':
            out.append(c)
            i += 1
            continue
        n = field[i + 1] if i + 1 < len(field) else ''
        if n in ESC:
            out.append(ESC[n])
            i += 2
        elif n == 'x':
            m = re.match(r'[0-9A-Fa-f]{1,2}', field[i + 2:])
            out.append(chr(int(m.group(0), 16)))
            i += 2 + len(m.group(0))
        elif n.isdigit():
            m = re.match(r'[0-7]{1,3}', field[i + 1:])
            out.append(chr(int(m.group(0), 8)))
            i += 1 + len(m.group(0))
        else:
            out.append(n)
            i += 2
    return ''.join(out)


def boolean_columns(create_sql):
    """Column names declared boolean in a CREATE TABLE statement."""
    body = create_sql[create_sql.find('(') + 1:create_sql.rfind(')')]
    cols = set()
    for line in body.split('\n'):
        m = re.match(r'\s*"?([A-Za-z_][A-Za-z0-9_]*)"?\s+boolean\b', line)
        if m:
            cols.add(m.group(1))
    return cols


def main(dump, out):
    f, r, meta, entries = read_toc(dump)
    creates = {(e['namespace'], e['tag']): e['defn'] or '' for e in entries if e['desc'] == 'TABLE'}
    if os.path.exists(out):
        os.remove(out)
    db = sqlite3.connect(out)
    for e in entries:
        key = (e['namespace'], e['tag'])
        if e['desc'] != 'TABLE DATA' or key not in WANTED:
            continue
        cols = [c.strip().strip('"') for c in re.search(r'\((.*)\)\s+FROM stdin', e['copy'], re.S).group(1).split(',')]
        bools = boolean_columns(creates.get(key, ''))
        name = WANTED[key]
        db.execute(f'CREATE TABLE "{name}" (' + ', '.join(f'"{c}" TEXT' for c in cols) + ')')
        f.seek(e['data_offset'])
        r.byte()
        r.int()
        chunks = []
        while True:
            n = r.int()
            if n == 0:
                break
            chunks.append(f.read(n))
        text = decompress(b''.join(chunks), meta['compression']).decode('utf-8') if chunks else ''
        rows = []
        for line in text.split('\n'):
            if line == '' or line == r'\.':
                continue
            vals = [unescape(v) for v in line.split('\t')]
            for i, c in enumerate(cols):
                if c in bools and vals[i] is not None:
                    vals[i] = '1' if vals[i] == 't' else '0'
            rows.append(vals)
        db.executemany(f'INSERT INTO "{name}" VALUES (' + ','.join('?' * len(cols)) + ')', rows)
        print(f'{name:<24} {len(rows):>7} rows  ({len(bools)} boolean cols)')
    db.commit()
    db.close()


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
