<?php

use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\ResultStores\CacheHealthResultStore;

return [
    'oh_dear_endpoint' => [
        'enabled' => env('HEALTH_OH_DEAR_ENDPOINT_ENABLED', false),
        'secret' => env('HEALTH_OH_DEAR_SECRET'),
        'url' => '/health',
    ],

    'result_stores' => [
        CacheHealthResultStore::class => [
            'store' => 'file',
        ],
    ],

    'checks' => [
        DatabaseCheck::class,
        UsedDiskSpaceCheck::class => [
            'warnWhenUsedSpaceIsAbovePercentage' => 70,
            'failWhenUsedSpaceIsAbovePercentage' => 90,
        ],
        CacheCheck::class,
    ],

    'notifications' => [
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', false),
    ],
];
