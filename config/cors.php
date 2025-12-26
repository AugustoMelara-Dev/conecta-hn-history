<?php

/**
 * CORS (Cross-Origin Resource Sharing) Configuration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STATEFUL SPA AUTHENTICATION: Inertia.js + Laravel Sanctum Support     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY CONTEXT:
 * Inertia.js uses stateful session-based authentication (cookies), NOT
 * stateless API tokens. The browser requires Access-Control-Allow-Credentials
 * header to send cookies cross-origin.
 *
 * CRITICAL SETTINGS:
 * - supports_credentials: true (enables cookie transmission)
 * - allowed_origins: Must match your frontend domains exactly
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('APP_URL', 'http://localhost'),
        'http://localhost:3000',  // Vite dev server
        'http://localhost:5173',  // Vite default port
        'http://127.0.0.1:8000',
    ],

    'allowed_origins_patterns' => [
        // Allow all subdomains for multi-tenant support
        '/^https?:\/\/.*\.'.preg_quote(parse_url(env('APP_URL', 'localhost'), PHP_URL_HOST), '/').'$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Credentials Support (CRITICAL for Inertia/Sanctum)
    |--------------------------------------------------------------------------
    |
    | When set to true, the Access-Control-Allow-Credentials header is sent.
    | This is REQUIRED for stateful SPA authentication with cookies/sessions.
    |
    */
    'supports_credentials' => true,

];
