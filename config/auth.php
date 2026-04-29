<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    /*
    |--------------------------------------------------------------------------
    | Super Admin Configuration (Security Critical)
    |--------------------------------------------------------------------------
    |
    | Define super admin emails via APP_SUPER_ADMINS environment variable.
    | Multiple emails separated by commas: "admin1@example.com,admin2@example.com"
    |
    | SECURITY: Never hardcode admin emails in source code.
    | AUDIT: Changes to this list should trigger security review.
    |
    */

    'super_admins' => array_filter(
        array_map('trim', explode(',', env('APP_SUPER_ADMINS', '')))
    ),

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
            'expire' => 120,
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins', // Segregated provider for Filament
            'expire' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Infrastructure\Identity\Models\FilamentUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    'idempotency_processing_lock_ttl_seconds' => env('AUTH_IDEMPOTENCY_PROCESSING_LOCK_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | OTP (One-Time Password) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OTP-based authentication including passwordless login,
    | two-factor authentication, and verification flows. All values are
    | configurable via environment variables for 12-Factor compliance.
    |
    | CRITICAL: These values are required by the Application Layer.
    | Missing values will cause a RuntimeException during service resolution.
    |
    */

    'otp' => [
        // How long before an OTP expires (minutes)
        'expiry_minutes' => env('AUTH_OTP_EXPIRY_MINUTES', 10),

        // Maximum OTP requests allowed per hour per identifier
        'max_requests_per_hour' => env('AUTH_OTP_MAX_REQUESTS_PER_HOUR', 3),

        // Length of the numeric OTP code
        'length' => env('AUTH_OTP_LENGTH', 6),

        // Rate limit decay window in seconds (1 hour = 3600)
        'rate_limit_decay_seconds' => env('AUTH_OTP_RATE_LIMIT_DECAY', 3600),

        // Maximum failed verification attempts before OTP is invalidated
        'max_attempts' => env('AUTH_OTP_MAX_ATTEMPTS', 5),

        // Idempotency TTL for OTP generation (24 hours = 86400)
        'idempotency_ttl_seconds' => env('AUTH_OTP_IDEMPOTENCY_TTL', 86400),

        // Optional tenant UUID used when OTP is requested from a public surface
        // before a tenant context exists. If blank, the controller falls back
        // to an existing tenant and returns that tenant_id to the client.
        'platform_tenant_id' => env('AUTH_OTP_PLATFORM_TENANT_ID'),

        // Magic-link OTP login authenticates the device that opens the signed
        // email link, so it stays disabled until a real smoke test enables it.
        'magic_link_enabled' => env('AUTH_MAGIC_LINK_ENABLED', false),
        'magic_link_expiry_minutes' => env('AUTH_MAGIC_LINK_EXPIRY_MINUTES', env('AUTH_OTP_EXPIRY_MINUTES', 10)),
        'magic_link_tenant_allowlist' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AUTH_MAGIC_LINK_TENANT_ALLOWLIST', ''))
        ))),
    ],

];
