<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Part 4.6: ticket QR PNGs, public, object names "<code>.png" (R-066).
        // Served at /storage/qr-codes/<code>.png so n8n templates keep working (F-NTF-023).
        'qr' => [
            'driver' => 'local',
            'root' => storage_path('app/public/qr-codes'),
            'url' => env('APP_URL').'/storage/qr-codes',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Branding images (logo, hero, header texture, before-footer, og) — public.
        'branding' => [
            'driver' => 'local',
            'root' => storage_path('app/public/branding'),
            'url' => env('APP_URL').'/storage/branding',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Card-terminal slips — PRIVATE, 5-minute signed URLs only (D-006, T-123).
        'receipts' => [
            'driver' => 'local',
            'root' => storage_path('app/private/receipts'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        // Generated exports (Excel/PDF) — private, cleaned after 24 h.
        'exports' => [
            'driver' => 'local',
            'root' => storage_path('app/private/exports'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

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

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
