<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration values for the sales export system.
    |
    */

    // Maximum date range allowed for exports (prevents DoS attacks)
    'max_date_range_days' => env('EXPORT_MAX_DATE_RANGE_DAYS', 365),

    // Default date range when no dates are specified
    'default_date_range_days' => env('EXPORT_DEFAULT_DATE_RANGE_DAYS', 30),

    // Chunk size for memory-efficient processing
    // 500 records ≈ 1MB per chunk, safe for most server configs
    'chunk_size' => env('EXPORT_CHUNK_SIZE', 500),

    // Order number prefix
    'order_number_prefix' => env('EXPORT_ORDER_NUMBER_PREFIX', '#'),

    // Rate limiting (requests per minute per user)
    'rate_limit' => env('EXPORT_RATE_LIMIT', 5),
];
