<?php

return [
    'oh_dear_endpoint' => [
        'enabled' => env('HEALTH_OH_DEAR_ENDPOINT_ENABLED', false),
        'secret' => env('HEALTH_OH_DEAR_SECRET'),
        'url' => '/health',
    ],

    'result_stores' => [
        Spatie\Health\ResultStores\CacheHealthResultStore::class => [
            'store' => 'file',
        ],
    ],

    'checks' => [
        Spatie\Health\Checks\Checks\DatabaseCheck::class,
        Spatie\Health\Checks\Checks\UsedDiskSpaceCheck::class => [
            'warnWhenUsedSpaceIsAbovePercentage' => 70,
            'failWhenUsedSpaceIsAbovePercentage' => 90,
        ],
        Spatie\Health\Checks\Checks\CacheCheck::class,
    ],

    'notifications' => [
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', false),
    ],
];
