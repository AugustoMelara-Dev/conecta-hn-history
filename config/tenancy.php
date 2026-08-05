<?php

declare(strict_types=1);

/**
 * Tenancy Configuration (ADR-004 Extension)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPEN/CLOSED PRINCIPLE: Route patterns are configuration, not code      ║
 * ║  Changes here don't require middleware redeployment                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * ADDING NEW ROUTES:
 * 1. Add pattern to context_routes array below
 * 2. Clear config cache: php artisan config:clear
 * 3. NO CODE CHANGES REQUIRED
 */

return [

    'postgres_rls' => [
        'enabled' => (bool) env('POSTGRES_RLS_ENABLED', false),
        'verification_enabled' => (bool) env('RUN_POSTGRES_RLS_TESTS', false),
    ],
    /*
    |--------------------------------------------------------------------------
    | Tenant Context Routes
    |--------------------------------------------------------------------------
    |
    | Route patterns where tenant context is allowed to be shared via Inertia.
    | Uses Laravel route name patterns (supports wildcards).
    |
    | Example patterns:
    | - 'storefront.*' matches all routes starting with 'storefront.'
    | - 'checkout.index' matches only 'checkout.index'
    | - 'tienda.{slug}.*' would match dynamic slugs
    |
    */

    'context_routes' => [
        'storefront.*',
        'checkout.*',
        'cart.*',
        'tienda.*',
        'productos.*',
        'tenant.catalog-imports.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for how tenants are resolved from requests.
    |
    */

    'resolution' => [
        // Base domain for subdomain extraction
        'base_domain' => env('TENANT_BASE_DOMAIN', 'localhost'),

        // Header name for API tenant resolution
        'header_name' => env('TENANT_HEADER_NAME', 'X-Tenant-ID'),

        // Route parameter names to check (in priority order)
        'route_parameters' => ['tenant', 'tenant_slug', 'negocio'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Data Sharing
    |--------------------------------------------------------------------------
    |
    | What tenant data is shared via Inertia props.
    |
    */

    'sharing' => [
        // Include banking info in checkout routes only
        'banking_routes' => [
            'checkout.payment',
            'checkout.confirm',
        ],

        // Maximum announcements to share
        'max_announcements' => 5,
    ],

];
