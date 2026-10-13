<?php

/*
| Every app disk can live on the server (STORAGE_DRIVER=local, default) or in
| S3-compatible object storage (STORAGE_DRIVER=s3) — required on hosts whose
| filesystem is not persistent (Laravel Cloud, containers without volumes).
| Public disks use the public bucket (AWS_*); private disks use the private
| bucket (AWS_PRIVATE_*, falling back to AWS_*). Each disk keeps its own prefix.
*/
$s3 = env('STORAGE_DRIVER', 'local') === 's3';

$disk = function (string $localRoot, string $prefix, string $visibility, ?string $url = null) use ($s3): array {
    if (! $s3) {
        return array_filter([
            'driver' => 'local',
            'root' => storage_path($localRoot),
            'url' => $url,
            'visibility' => $visibility,
            'throw' => false,
            'report' => false,
        ], fn ($v) => $v !== null);
    }
    $private = $visibility === 'private';
    $p = fn (string $key, mixed $default = null) => $private ? env('AWS_PRIVATE_'.$key, env('AWS_'.$key, $default)) : env('AWS_'.$key, $default);

    return [
        'driver' => 's3',
        'key' => $p('ACCESS_KEY_ID'),
        'secret' => $p('SECRET_ACCESS_KEY'),
        'region' => $p('DEFAULT_REGION', 'auto'),
        'bucket' => $p('BUCKET'),
        'url' => $private ? null : (env('AWS_URL') ? rtrim((string) env('AWS_URL'), '/').($prefix !== '' ? '/'.$prefix : '') : null),
        'endpoint' => $p('ENDPOINT'),
        'use_path_style_endpoint' => (bool) $p('USE_PATH_STYLE_ENDPOINT', false),
        'root' => $prefix,
        'visibility' => $visibility,
        'throw' => false,
        'report' => false,
    ];
};

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => ['serve' => ! $s3] + $disk('app/private', 'app', 'private'),

        'public' => $disk('app/public', 'public', 'public', env('APP_URL').'/storage'),

        // Part 4.6: ticket QR PNGs, public, object names "<code>.png" (R-066).
        // Served at /storage/qr-codes/<code>.png locally so n8n templates keep working (F-NTF-023).
        'qr' => $disk('app/public/qr-codes', 'qr-codes', 'public', env('APP_URL').'/storage/qr-codes'),

        // Branding images (logo, hero, header texture, before-footer, og) — public.
        'branding' => $disk('app/public/branding', 'branding', 'public', env('APP_URL').'/storage/branding'),

        // Card-terminal slips — PRIVATE, 5-minute signed URLs only (D-006, T-123).
        'receipts' => $disk('app/private/receipts', 'receipts', 'private'),

        // Generated exports (Excel/PDF) — private, cleaned after 24 h.
        'exports' => $disk('app/private/exports', 'exports', 'private'),

        'backups' => [
            'driver' => env('BACKUP_DISK_DRIVER', 'local'),
            'root' => storage_path('app/private/backups'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
