<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'chat_completions_endpoint' => env('OPENAI_CHAT_COMPLETIONS_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        'embeddings_endpoint' => env('OPENAI_EMBEDDINGS_ENDPOINT', 'https://api.openai.com/v1/embeddings'),
        'catalog_vision_model' => env('OPENAI_CATALOG_VISION_MODEL', 'gpt-4o-mini'),
        'catalog_input_usd_per_million' => env('OPENAI_CATALOG_INPUT_USD_PER_MILLION', 0.15),
        'catalog_output_usd_per_million' => env('OPENAI_CATALOG_OUTPUT_USD_PER_MILLION', 0.60),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Authentication (OAuth)
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    | Webhook signature verification for payment providers.
    | The secret must match the value configured in your payment dashboard.
    */

    'payment' => [
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        'provider' => env('PAYMENT_PROVIDER', 'generic'), // stripe, paypal, local
        'webhook_require_timestamp' => env('PAYMENT_WEBHOOK_REQUIRE_TIMESTAMP', true),
        'webhook_timestamp_tolerance_seconds' => env('PAYMENT_WEBHOOK_TIMESTAMP_TOLERANCE_SECONDS', 300),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', env('PAYMENT_WEBHOOK_SECRET')),
        'price_pro' => env('STRIPE_PRICE_PRO'),
    ],

    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    ],

    'analytics' => [
        'salt' => env('ANALYTICS_SALT', 'conecta-analytics-default-salt'),
    ],

    'otp_attack' => [
        'database_path' => env('OTP_ATTACK_DB_PATH'),
    ],

];
