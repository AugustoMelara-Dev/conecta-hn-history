<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveTenantFromRequest - Single Source of Truth for Tenant Context.
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PERFORMANCE ARCHITECTURE: Cache-Aside Pattern (Host/Slug Resolution)  ║
 * ║  SECURITY: Negative Caching to prevent DoS (DB Exhaustion)             ║
 * ║  MEMORY: Hydrates only essential identity columns (id, slug, is_active) ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ResolveTenantFromRequest
{
    /**
     * Cache TTL for valid tenants (1 hour).
     * High hit-ratio optimization.
     */
    private const TTL_VALID = 3600;

    /**
     * Cache TTL for invalid lookups (5 minutes).
     * DoS prevention against subdomain flooding.
     */
    private const TTL_INVALID = 300;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            // No tenant context - allowed for public routes.
            // Strict 404 handled by EnsureTenantOwnership/EnsureUserBelongsToTenant if route requires it.
            return $next($request);
        }

        if (! $tenant->is_active) {
            abort(404); // Or specific suspended page
        }

        // VC-AUDIT-108: Prevent unauthorized header-based context switching
        if ($this->isHeaderBasedResolution($request) && auth()->check()) {
            if (! auth()->user()->tenants()->where('tenants.id', $tenant->id)->exists()) {
                \Illuminate\Support\Facades\Log::warning('TenantResolution: Unauthorized header access', [
                    'user_id' => auth()->id(),
                    'target_tenant' => $tenant->id,
                ]);
                abort(403, 'Unauthorized access to this tenant context.');
            }
        }

        // Bind to container - Single Source of Truth
        app()->instance('currentTenant', $tenant);

        // Optional: Share with views if global state is needed
        // View::share('currentTenant', $tenant);

        return $next($request);
    }

    /**
     * Resolve tenant with Cache-Aside protection.
     * Implements Granular TTL for Positive vs Negative results.
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        // 1. Identify Resolution Strategy (Cache Key)
        $cacheKey = $this->getCacheKey($request);

        if (! $cacheKey) {
            return null;
        }

        // 2. Cache Hit Check
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            // Check if it's a Negative Cache Hit
            if ($cached === 'NOT_FOUND') {
                return null;
            }

            // VC-AUDIT-106: Rehydrate from lightweight array
            if (is_array($cached)) {
                $tenant = new Tenant;
                $tenant->forceFill($cached);
                $tenant->exists = true;

                return $tenant;
            }

            return $cached; // Backwards compatibility for old cache entries
        }

        // 3. Cache Miss - Database Query (Optimized Memory Footprint)
        // Select ONLY essential columns to keep Redis serialization light.
        // 3. Cache Miss - Database Query (Optimized Memory Footprint)
        // REVERTED to select('*') because 'is_pro' accessor requires 'plan_type', 'subscription_status', etc.
        // Explicit selection caused MissingAttributeException on computed properties.
        $query = Tenant::query()
            ->select('*')
            ->take(1);

        $this->applyResolutionLogic($query, $request);

        /** @var Tenant|null $tenant */
        $tenant = $query->first();

        // 4. Store Result (Cache-Aside)
        if ($tenant) {
            // VC-AUDIT-106: Cache lightweight array instead of full Eloquent model
            $payload = [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                // 'domain' removed - property does not exist
                'name' => $tenant->name,
                'is_active' => $tenant->is_active,
                'is_pro' => $tenant->is_pro,
                'logo_path' => $tenant->logo_path,
            ];
            Cache::put($cacheKey, $payload, self::TTL_VALID);
        } else {
            // Negative Cache: 5 Minutes (DoS Protection)
            // Stores a lightweight sentinel value
            Cache::put($cacheKey, 'NOT_FOUND', self::TTL_INVALID);
        }

        return $tenant;
    }

    /**
     * Determine unique cache key based on Request context.
     * Returns null if no resolution criteria found (e.g. naked IP access).
     */
    private function getCacheKey(Request $request): ?string
    {
        // Strategy A: Route Parameter
        $routeParam = $this->getRouteParam($request);
        if ($routeParam) {
            return "tenant_resolver:route:{$routeParam}";
        }

        // Strategy B: Subdomain
        $subdomain = $this->getSubdomain($request);
        if ($subdomain) {
            return "tenant_resolver:subdomain:{$subdomain}";
        }

        // Strategy C: Header (API)
        $headerId = $this->getHeaderId($request);
        if ($headerId) {
            return "tenant_resolver:header:{$headerId}";
        }

        return null;
    }

    /**
     * Apply specific query logic based on available context.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyResolutionLogic($query, Request $request): void
    {
        // Strategy A: Route Parameter
        $routeParam = $this->getRouteParam($request);
        if ($routeParam) {
            $query->where('slug', $routeParam);

            return;
        }

        // Strategy B: Subdomain
        $subdomain = $this->getSubdomain($request);
        if ($subdomain) {
            $query->where('slug', $subdomain);

            return;
        }

        // Strategy C: Header
        $headerId = $this->getHeaderId($request);
        if ($headerId) {
            $query->where('id', $headerId);

            return;
        }

        // Failsafe
        $query->whereRaw('1=0');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONTEXT EXTRACTORS (Config Driven)
    // ═══════════════════════════════════════════════════════════════════════

    private function getRouteParam(Request $request): ?string
    {
        $params = config('tenancy.resolution.route_parameters', ['tenant', 'tenant_slug']);
        foreach ($params as $param) {
            if ($value = $request->route($param)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function getSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $base = config('tenancy.resolution.base_domain', 'localhost');
        $suffix = '.'.$base;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $sub = \Illuminate\Support\Str::beforeLast($host, $suffix);

        return ($sub && $sub !== 'www' && ! str_contains($sub, '.')) ? $sub : null;
    }

    private function getHeaderId(Request $request): ?string
    {
        return $request->header(config('tenancy.resolution.header_name', 'X-Tenant-ID'));
    }

    /**
     * VC-AUDIT-108: Check if tenant was resolved via header strategy.
     */
    private function isHeaderBasedResolution(Request $request): bool
    {
        // Header-based if no route param or subdomain, but header is present
        return $this->getRouteParam($request) === null
            && $this->getSubdomain($request) === null
            && $this->getHeaderId($request) !== null;
    }
}
