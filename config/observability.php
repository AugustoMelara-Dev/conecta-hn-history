<?php

return [
    'error_tracking' => [
        'provider' => env('ERROR_TRACKING_PROVIDER', 'sentry'),
        'sentry_dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
        'frontend_dsn' => env('VITE_SENTRY_DSN'),
        'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'local')),
        'release' => env('SENTRY_RELEASE'),
    ],

    'logs' => [
        'provider' => env('LOG_AGGREGATION_PROVIDER', 'better_stack'),
        'better_stack_source_token' => env('BETTER_STACK_SOURCE_TOKEN'),
        'better_stack_ingesting_host' => env('BETTER_STACK_INGESTING_HOST'),
    ],

    'channels' => [
        'whatsapp' => env('CUSTOMER_AUTOMATION_WHATSAPP_PROVIDER', 'whatsapp_cloud'),
        'email' => env('MAIL_MAILER', 'log'),
        'sms' => env('SMS_MARKETING_PROVIDER', 'missing'),
        'payments' => env('PAYMENT_PROVIDER', 'generic'),
    ],
];
