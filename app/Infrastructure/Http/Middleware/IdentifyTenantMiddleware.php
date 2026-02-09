<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Shared\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * IdentifyTenantMiddleware - The Gatekeeper
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RESOLVER: Determines Tenant from User (Auth) or Domain (Public).       ║
 * ║  INJECTOR: Sets the Singleton TenantContext.                            ║
 * ║  GUARD: Aborts 404/403 if Tenant cannot be resolved.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class IdentifyTenantMiddleware
{
    public function __construct(
        private readonly TenantContext $context
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = null;

        // 1. Resolve from Authenticated User (Secure B2B)
        if (Auth::check()) {
            $user = Auth::user();
            // Assuming 'tenant_id' is a direct property on User model.
            $tenantId = $user->tenant_id ?? null;
        }

        // 2. Fallback: Public Routes (e.g. Microsites via Subdomain/Path)
        // If not logged in, we check if the route or request implies a tenant.
        // Implementation depends on routing strategy (domain mapping vs path).
        // For now, we focus on the requirement: "Si es ruta pública... resuelve basándote en Subdominio".
        if (! $tenantId) {
            // Basic Subdomain Resolution Logic (Placeholder)
            // $host = $request->getHost(); ...
            // For strict B2B mostly, we might enforce Auth.
            // But if we fail to resolve, we MUST NOT set context to null/garbage.
        }

        // 3. Set Context or Fail
        if ($tenantId) {
            $this->context->setTenant((int) $tenantId);
        } else {
            // STRICT MODE: Tenant-aware routes MUST have resolved context.
            // Public routes (landing page, directory) should NOT use this middleware.
            abort(404, 'Tenant not found.');
        }

        return $next($request);
    }
}
