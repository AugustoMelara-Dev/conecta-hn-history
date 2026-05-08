<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InitializeFilamentTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant instanceof Tenant && ! $this->tenantContext->isInitialized()) {
            $this->tenantContext->setTenant((string) $tenant->getKey());
            app()->instance('currentTenant', $tenant);
            app()->instance('current_tenant', $tenant);
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        try {
            $tenant = Filament::getTenant();

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        } catch (\Throwable) {
            // Filament may not have hydrated the tenant yet; fall back to the route parameter.
        }

        $routeTenant = $request->route('tenant');

        if ($routeTenant instanceof Tenant) {
            return $routeTenant;
        }

        if (is_string($routeTenant) && $routeTenant !== '') {
            return Tenant::query()
                ->where('slug', $routeTenant)
                ->first();
        }

        return null;
    }
}
