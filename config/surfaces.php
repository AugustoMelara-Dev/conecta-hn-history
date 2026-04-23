<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Surface Origins
    |--------------------------------------------------------------------------
    |
    | Origins used during the progressive split to user-app, business-app and
    | platform-api. When omitted, relative paths are returned.
    |
    */
    'origins' => [
        'user_app' => env('USER_APP_URL', env('PLATFORM_DIRECTORY_URL')),
        'business_app' => env('BUSINESS_APP_URL', env('PLATFORM_APP_URL')),
        'admin_app' => env('ADMIN_APP_URL', env('PLATFORM_APP_URL')),
        'platform_api' => env('PLATFORM_API_URL', env('APP_URL')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Compatibility Keys
    |--------------------------------------------------------------------------
    |
    | Explicit legacy entry points kept during strangler migration.
    |
    */
    'legacy_paths' => [
        '/dashboard',
        '/workspace/dashboard',
        '/workspace/products',
        '/workspace/orders',
        '/workspace/settings',
        '/workspace/billing',
    ],

    'migration_phase' => env('SURFACE_SPLIT_PHASE', 'strangler_v1'),
];
