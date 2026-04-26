<?php

declare(strict_types=1);

return [
    'http' => [
        'enabled' => (bool) env('PERF_HTTP_METRICS_ENABLED', false),
        'query_metrics_enabled' => (bool) env('PERF_QUERY_METRICS_ENABLED', false),
        'sample_rate' => (float) env('PERF_HTTP_SAMPLE_RATE', 1.0),
        'always_log_slow_ms' => (int) env('PERF_HTTP_ALWAYS_LOG_SLOW_MS', 1000),
    ],
];
