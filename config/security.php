<?php

declare(strict_types=1);

return [
    'admin_mfa_required' => (bool) env('SECURITY_ADMIN_MFA_REQUIRED', env('APP_ENV', 'production') === 'production'),
    'personal_access_token_ttl_days' => max(1, (int) env('SECURITY_TOKEN_TTL_DAYS', 30)),
    'impersonation' => [
        'ttl_minutes' => max(1, (int) env('SECURITY_IMPERSONATION_TTL_MINUTES', 15)),
        'mfa_max_age_seconds' => max(60, (int) env('SECURITY_IMPERSONATION_MFA_MAX_AGE_SECONDS', 600)),
    ],

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        // Enable only after every current and future subdomain is HTTPS-ready.
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
        // Preload is an operational commitment and remains opt-in.
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],
];
