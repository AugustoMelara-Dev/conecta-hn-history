<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureUserBelongsToTenant - Validates user-tenant relationship (ADR-004).
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SEPARATION OF CONCERNS:                                                ║
 * ║  - ResolveTenantFromRequest: Determines context from request           ║
 * ║  - EnsureUserBelongsToTenant: Validates user has access                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY:
 * - Mismatch = 403 Forbidden (explicit rejection)
 * - NO silent fallback
 * - NO user->tenant as alternative source
 *
 * PERFORMANCE:
 * - Uses short-lived in-request caching for the exists() query
 */
class EnsureUserBelongsToTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Guest users: allowed (guest checkout scenario)
        if (! $user instanceof User) {
            return $next($request);
        }

        // No tenant context: skip validation
        if (! app()->has('currentTenant')) {
            return $next($request);
        }

        $tenant = app('currentTenant');

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        // SECURITY: Validate user belongs to resolved tenant
        if (! $this->userBelongsToTenant($user, $tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }

        return $next($request);
    }

    /**
     * Check if user belongs to the resolved tenant.
     *
     * PERFORMANCE: Uses request-scoped cache to avoid N+1 queries.
     */
    private function userBelongsToTenant(User $user, Tenant $tenant): bool
    {
        // Admin users may have access to all tenants
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // FIX: Use isset() which works with Eloquent magic attributes
        // property_exists() does NOT work for $attributes array values
        if (isset($user->tenant_id)) {
            return (string) $user->tenant_id === (string) $tenant->id;
        }

        // PERFORMANCE: Cache the belongs check for this request
        // Avoids repeated SQL queries during same request lifecycle
        $cacheKey = sprintf('tenant_belongs:%s:%s', (string) $user->id, (string) $tenant->id);

        return Cache::store('array')->remember(
            $cacheKey,
            60, // 1 minute (effectively request-scoped with array driver)
            fn () => $user->tenants()->whereKey((string) $tenant->id)->exists()
        );
    }
}
