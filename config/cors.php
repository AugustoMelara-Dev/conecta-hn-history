<?php

/**
 * CORS (Cross-Origin Resource Sharing) Configuration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ZERO-TRUST CORS: Whitelist-Only Cross-Origin Access                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY CONTEXT:
 * - Production: Only explicitly whitelisted domains allowed
 * - Development: Localhost ports for Vite dev server
 * - Credentials: Enabled for Sanctum/Inertia stateful auth
 *
 * THREAT MITIGATION:
 * - Prevents unauthorized API access from malicious sites
 * - Blocks data exfiltration via cross-origin requests
 * - Protects session cookies from cross-site theft
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Paths Subject to CORS
    |--------------------------------------------------------------------------
    | Only these paths will have CORS headers applied.
    | API endpoints and authentication routes need cross-origin access.
    */
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register',
        'broadcasting/auth', // WebSocket authentication
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed HTTP Methods
    |--------------------------------------------------------------------------
    | Restrict to only methods actually used by the application.
    */
    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins (STRICT WHITELIST)
    |--------------------------------------------------------------------------
    | SECURITY: Only explicitly trusted domains can make cross-origin requests.
    | Never use '*' with credentials enabled - it will be rejected by browsers.
    */
    'allowed_origins' => array_filter([
        env('APP_URL', 'http://localhost'),
        // Development servers (only in local environment)
        env('APP_ENV') === 'local' ? 'http://localhost:3000' : null,
        env('APP_ENV') === 'local' ? 'http://localhost:5173' : null,
        env('APP_ENV') === 'local' ? 'http://127.0.0.1:8000' : null,
    ]),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns (Multi-Tenant Subdomain Support)
    |--------------------------------------------------------------------------
    | Regex patterns for dynamic tenant subdomains in production.
    */
    'allowed_origins_patterns' => [
        // Allow all subdomains of the main application domain
        '/^https?:\/\/.*\.' . preg_quote(parse_url(env('APP_URL', 'localhost'), PHP_URL_HOST), '/') . '$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers (Explicit Whitelist)
    |--------------------------------------------------------------------------
    | Only headers required by the application are allowed.
    */
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-Socket-ID', // Laravel Echo WebSockets
        'X-Inertia',
        'X-Inertia-Version',
        'X-Inertia-Partial-Data',
        'X-Inertia-Partial-Component',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    | Headers the browser is allowed to access from the response.
    */
    'exposed_headers' => [
        'X-Inertia',
    ],

    /*
    |--------------------------------------------------------------------------
    | Preflight Cache Duration (seconds)
    |--------------------------------------------------------------------------
    | How long browsers can cache preflight responses.
    | 7200 = 2 hours (reduces OPTIONS requests)
    */
    'max_age' => 7200,

    /*
    |--------------------------------------------------------------------------
    | Credentials Support (REQUIRED for Sanctum/Inertia)
    |--------------------------------------------------------------------------
    | When true, Access-Control-Allow-Credentials header is sent.
    | This is REQUIRED for stateful SPA authentication with cookies/sessions.
    | 
    | SECURITY: When true, allowed_origins CANNOT be '*'
    */
    'supports_credentials' => true,

];
