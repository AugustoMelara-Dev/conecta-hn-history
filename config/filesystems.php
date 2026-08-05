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
            'driver' => env('PRIVATE_DISK_DRIVER', 'local'),
            'key' => env('PRIVATE_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('PRIVATE_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('PRIVATE_AWS_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('PRIVATE_AWS_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('PRIVATE_AWS_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('PRIVATE_AWS_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'visibility' => env('PRIVATE_DISK_VISIBILITY', 'private'),
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => env('PRIVATE_DISK_THROW', true),
            'report' => env('PRIVATE_DISK_REPORT', true),
        ],

        'private' => [
            'driver' => env('PRIVATE_DISK_DRIVER', 'local'),
            'root' => storage_path('app/private'),
            'serve' => true,
            'key' => env('PRIVATE_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('PRIVATE_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('PRIVATE_AWS_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('PRIVATE_AWS_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('PRIVATE_AWS_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('PRIVATE_AWS_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'visibility' => env('PRIVATE_DISK_VISIBILITY', 'private'),
            'throw' => env('PRIVATE_DISK_THROW', true),
            'report' => env('PRIVATE_DISK_REPORT', true),
        ],

        'public' => [
            'driver' => env('PUBLIC_DISK_DRIVER', 'local'),
            'key' => env('PUBLIC_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('PUBLIC_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('PUBLIC_AWS_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('PUBLIC_AWS_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('PUBLIC_AWS_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('PUBLIC_AWS_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'root' => storage_path('app/public'),
            'url' => env('PUBLIC_DISK_URL', env('APP_URL').'/storage'),
            'visibility' => env('PUBLIC_DISK_VISIBILITY', 'public'),
            'throw' => env('PUBLIC_DISK_THROW', false),
            'report' => env('PUBLIC_DISK_REPORT', false),
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

        /*
        |--------------------------------------------------------------------------
        | Cloudflare R2 - Alternativa económica a S3 para Dovra AI
        |--------------------------------------------------------------------------
        | R2 es compatible con S3 API pero sin costos de egress.
        | Ideal para galerías ilimitadas del Plan PRO.
        |
        | Variables de entorno requeridas:
        | - R2_ACCESS_KEY_ID
        | - R2_SECRET_ACCESS_KEY
        | - R2_BUCKET
        | - R2_URL (URL pública del bucket)
        | - R2_ENDPOINT (https://<account_id>.r2.cloudflarestorage.com)
        */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Media Disk - Disco principal para MediaLibrary (Producción)
        |--------------------------------------------------------------------------
        | Este disco se usa para todas las imágenes de productos y tenants.
        | En desarrollo: usa 'public'
        | En producción: cambiar MEDIA_DISK a 's3' o 'r2'
        */
        'media' => [
            'driver' => env('MEDIA_DISK_DRIVER', 'local'),
            'key' => env('MEDIA_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('MEDIA_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('MEDIA_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('MEDIA_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('MEDIA_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('MEDIA_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'root' => storage_path('app/public'),
            'url' => env('MEDIA_DISK_URL', env('APP_URL').'/storage'),
            'visibility' => env('MEDIA_DISK_VISIBILITY', 'public'),
            'throw' => env('MEDIA_DISK_THROW', false),
            'report' => env('MEDIA_DISK_REPORT', false),
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
