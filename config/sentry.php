<?php

declare(strict_types=1);

use App\Infrastructure\Observability\SentryEventScrubber;

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT'),
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'send_default_pii' => false,
    'before_send' => [SentryEventScrubber::class, 'scrub'],
    'ignore_transactions' => ['/up'],
    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'livewire' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
        'notifications' => true,
    ],
    'tracing' => [
        'queue_job_transactions' => true,
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'sql_origin' => true,
        'views' => true,
        'livewire' => true,
        'http_client_requests' => true,
        'cache' => true,
        'redis_commands' => false,
        'notifications' => true,
        'missing_routes' => false,
        'continue_after_response' => true,
        'gen_ai' => false,
        'gen_ai_invoke_agent' => false,
        'gen_ai_chat' => false,
        'gen_ai_execute_tool' => false,
        'gen_ai_embeddings' => false,
        'default_integrations' => true,
    ],
];
