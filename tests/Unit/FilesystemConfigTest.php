<?php

/*
| Storage portability: local disks by default, S3-compatible buckets with
| STORAGE_DRIVER=s3 (Laravel Cloud / containers without persistent volumes).
*/

function loadDisks(array $env): array
{
    $backup = [];
    foreach ($env as $k => $v) {
        $backup[$k] = getenv($k);
        putenv($v === null ? $k : "{$k}={$v}");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
    try {
        return (require base_path('config/filesystems.php'))['disks'];
    } finally {
        foreach ($backup as $k => $v) {
            putenv($v === false ? $k : "{$k}={$v}");
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }
}

it('keeps every app disk on the server by default', function () {
    $disks = loadDisks(['STORAGE_DRIVER' => null]);

    foreach (['local', 'public', 'qr', 'branding', 'receipts', 'exports'] as $name) {
        expect($disks[$name]['driver'])->toBe('local');
    }
    expect($disks['receipts']['visibility'])->toBe('private');
});

it('moves public disks to the public bucket and private disks to the private bucket with STORAGE_DRIVER=s3', function () {
    $disks = loadDisks([
        'STORAGE_DRIVER' => 's3', 'AWS_BUCKET' => 'qcamel-public', 'AWS_URL' => 'https://cdn.qcamel.test/',
        'AWS_PRIVATE_BUCKET' => 'qcamel-private', 'AWS_PRIVATE_ACCESS_KEY_ID' => 'private-key',
    ]);

    expect($disks['qr'])->toMatchArray(['driver' => 's3', 'bucket' => 'qcamel-public', 'root' => 'qr-codes', 'url' => 'https://cdn.qcamel.test/qr-codes', 'visibility' => 'public'])
        ->and($disks['branding']['root'])->toBe('branding')
        ->and($disks['receipts'])->toMatchArray(['driver' => 's3', 'bucket' => 'qcamel-private', 'key' => 'private-key', 'root' => 'receipts', 'url' => null, 'visibility' => 'private'])
        ->and($disks['exports']['bucket'])->toBe('qcamel-private')
        ->and($disks['local']['serve'])->toBeFalse();
});

it('falls back to the public bucket credentials when no private bucket is configured', function () {
    $disks = loadDisks(['STORAGE_DRIVER' => 's3', 'AWS_BUCKET' => 'only-bucket', 'AWS_PRIVATE_BUCKET' => null]);

    expect($disks['receipts']['bucket'])->toBe('only-bucket')->and($disks['receipts']['root'])->toBe('receipts');
});
