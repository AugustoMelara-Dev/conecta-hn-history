<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Public API lifecycle contract
    |--------------------------------------------------------------------------
    |
    | Every public API version should expose its lifecycle in response headers.
    | Keep v1 active until a documented replacement exists and clients have a
    | migration window.
    |
    */

    'supported_versions' => ['v1'],

    'versions' => [
        'v1' => [
            'status' => env('API_V1_STATUS', 'active'),
            'sunset_at' => env('API_V1_SUNSET_AT'),
            'successor' => env('API_V1_SUCCESSOR'),
            'policy_url' => env('API_VERSION_POLICY_URL', env('APP_URL').'/docs/api/versioning'),
        ],
    ],
];
