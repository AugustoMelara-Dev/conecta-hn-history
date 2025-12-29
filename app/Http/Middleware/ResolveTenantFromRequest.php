<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveTenantFromRequest - Single Source of Truth for Tenant Context (ADR-004).
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CONFIG-DRIVEN: All parameters read from config/tenancy.php            ║
 * ║  TENANT RESOLUTION: Request-based ONLY, never from authenticated user  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * RESOLUTION ORDER (from config):
 * 1. Route parameter: {tenant} or {tenant_slug}
 * 2. Subdomain: {slug}.domain.com
 * 3. Header: X-Tenant-ID (for API clients)
 *
 * SECURITY:
 * - User->tenant relationship is IGNORED for resolution
 * - User validation happens in EnsureUserBelongsToTenant (separate middleware)
 * - Mismatch = 403 Forbidden, not silent fallback
 */
class ResolveTenantFromRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            // No tenant context - allowed for public routes
            return $next($request);
        }

        // Bind to container - this is the ONLY place tenant gets bound
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }

    /**
     * Resolve tenant from request context.
     * NEVER uses authenticated user for resolution.
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        // Priority 1: Route parameter (from config)
        $tenantFromRoute = $this->resolveFromRoute($request);
        if ($tenantFromRoute !== null) {
            return $tenantFromRoute;
        }

        // Priority 2: Subdomain
        $tenantFromSubdomain = $this->resolveFromSubdomain($request);
        if ($tenantFromSubdomain !== null) {
            return $tenantFromSubdomain;
        }

        // Priority 3: Header (API clients)
        $tenantFromHeader = $this->resolveFromHeader($request);
        if ($tenantFromHeader !== null) {
            return $tenantFromHeader;
        }

        return null;
    }

    /**
     * Resolve tenant from route parameters.
     * Parameters are CONFIG-DRIVEN (Issue #7 fix).
     */
    private function resolveFromRoute(Request $request): ?Tenant
    {
        // Config-driven: Parameters from config/tenancy.php
        $parameterNames = config('tenancy.resolution.route_parameters', ['tenant', 'tenant_slug']);

        $slug = null;
        foreach ($parameterNames as $paramName) {
            $value = $request->route($paramName);
            if ($value !== null) {
                $slug = $value;
                break;
            }
        }

        if ($slug === null) {
            return null;
        }

        return Tenant::where('slug', $slug)->first();
    }

    /**
     * Resolve tenant from subdomain.
     * Base domain is CONFIG-DRIVEN.
     *
     * SECURITY: Uses Str::beforeLast to prevent injection via crafted hostnames.
     * Example: tienda.tienda.com with base "tienda.com" -> "tienda" (not corrupted)
     */
    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        // Config-driven: Base domain from config/tenancy.php
        $baseDomain = config('tenancy.resolution.base_domain', 'localhost');

        // Construct the full suffix to match
        $suffix = '.'.$baseDomain;

        // SECURITY: Must end with EXACTLY the suffix (not just contain it)
        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        // SAFE EXTRACTION: Use Str::beforeLast to get only the subdomain portion
        // Example: "shop.tienda.com" with suffix ".tienda.com" -> "shop"
        $subdomain = \Illuminate\Support\Str::beforeLast($host, $suffix);

        // Validation: Subdomain must not be empty or "www"
        if ($subdomain === '' || $subdomain === 'www') {
            return null;
        }

        // Validation: Subdomain should not contain dots (prevent multi-level)
        if (str_contains($subdomain, '.')) {
            return null;
        }

        return Tenant::where('slug', $subdomain)->first();
    }

    /**
     * Resolve tenant from header.
     * Header name is CONFIG-DRIVEN.
     */
    private function resolveFromHeader(Request $request): ?Tenant
    {
        // Config-driven: Header name from config/tenancy.php
        $headerName = config('tenancy.resolution.header_name', 'X-Tenant-ID');
        $tenantId = $request->header($headerName);

        if ($tenantId === null) {
            return null;
        }

        return Tenant::find((int) $tenantId);
    }
}
