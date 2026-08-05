<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Tenancy\PostgresTenantScope;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * IdentifyTenantBySubdomain Middleware
 *
 * Resolves the current tenant from the request hostname subdomain.
 * Must execute BEFORE HandleInertiaRequests to ensure tenant context
 * is available for shared Inertia data.
 *
 * Architecture: Subdomain-based Multi-Tenancy
 * - Root domain (conecta.hn) → Landing/Marketing
 * - Reserved subdomains (app, admin, api) → Central services
 * - Wildcard subdomains ({slug}.conecta.hn) → Tenant microsites
 */
final class IdentifyTenantBySubdomain
{
    /**
     * Reserved subdomains that should NOT trigger tenant resolution.
     * These are protected system namespaces.
     */
    private const RESERVED_SUBDOMAINS = [
        'app',      // Business OS Console
        'admin',    // Filament Admin Panel
        'api',      // API Gateway
        'www',      // WWW redirect
        'dev',      // Development
        'staging',  // Staging environment
        'mail',     // Email services
        'static',   // Static assets CDN
        'assets',   // Asset delivery
        'cdn',      // CDN subdomain
        'login',    // Auth flows
        'join',     // Onboarding
        'vito',     // Legacy brand protection
    ];

    public function __construct(private readonly PostgresTenantScope $postgresTenantScope) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $baseDomain = config('app.domain');

        // Skip if base domain not configured
        if (empty($baseDomain)) {
            return $next($request);
        }

        // Extract subdomain by removing base domain
        $subdomain = $this->extractSubdomain($host, $baseDomain);

        // Skip if no subdomain (root domain request)
        if ($subdomain === null || $subdomain === '') {
            return $next($request);
        }

        // Skip reserved subdomains (pass through without tenant resolution)
        if ($this->isReservedSubdomain($subdomain)) {
            return $next($request);
        }

        // Resolve tenant from subdomain slug
        $tenant = Tenant::query()
            ->where('slug', $subdomain)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            Log::warning('Subdomain routing: Tenant not found or inactive', [
                'subdomain' => $subdomain,
                'host' => $host,
                'ip' => $request->ip(),
            ]);

            abort(404, 'Business not found');
        }

        // Bind tenant to container as singleton
        app()->instance('current_tenant', $tenant);
        app(TenantContext::class)->setTenant((string) $tenant->id);

        return $this->postgresTenantScope->run(fn (): Response => $next($request));
    }

    /**
     * Extract subdomain from hostname.
     *
     * Examples:
     * - "barberia.conecta.hn" with base "conecta.hn" → "barberia"
     * - "conecta.hn" with base "conecta.hn" → null
     * - "app.conecta.hn" with base "conecta.hn" → "app"
     */
    private function extractSubdomain(string $host, string $baseDomain): ?string
    {
        // Normalize: remove port if present
        $host = strtolower(preg_replace('/:\d+$/', '', $host));
        $baseDomain = strtolower($baseDomain);

        // Check if host ends with base domain
        if (! str_ends_with($host, $baseDomain)) {
            return null;
        }

        // If host equals base domain exactly, no subdomain
        if ($host === $baseDomain) {
            return null;
        }

        // Extract subdomain (everything before .baseDomain)
        $subdomain = str_replace('.'.$baseDomain, '', $host);

        // Handle nested subdomains (e.g., "www.barberia" → take first segment only)
        if (str_contains($subdomain, '.')) {
            $parts = explode('.', $subdomain);
            $subdomain = end($parts); // Take rightmost segment before base
        }

        return $subdomain ?: null;
    }

    /**
     * Check if subdomain is in the reserved list.
     */
    private function isReservedSubdomain(string $subdomain): bool
    {
        return in_array(strtolower($subdomain), self::RESERVED_SUBDOMAINS, true);
    }
}
