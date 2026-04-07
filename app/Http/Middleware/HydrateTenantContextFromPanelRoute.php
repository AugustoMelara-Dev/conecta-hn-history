<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class HydrateTenantContextFromPanelRoute
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant instanceof Tenant && $this->userCanAccessTenant($request, $tenant)) {
            Filament::setTenant($tenant);
            $this->tenantContext->setTenant((string) $tenant->getKey());
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $routeTenant = $request->route('tenant');

        if ($routeTenant instanceof Tenant) {
            return $routeTenant;
        }

        if (is_string($routeTenant) && $routeTenant !== '') {
            return $this->findTenant($routeTenant);
        }

        $refererPath = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);

        if (! is_string($refererPath) || $refererPath === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($refererPath, '/'))));

        if (($segments[0] ?? null) !== 'app' || empty($segments[1])) {
            return null;
        }

        return $this->findTenant($segments[1]);
    }

    private function findTenant(string|int $routeTenant): ?Tenant
    {
        return Tenant::query()
            ->whereKey($routeTenant)
            ->orWhere('slug', (string) $routeTenant)
            ->first();
    }

    private function userCanAccessTenant(Request $request, Tenant $tenant): bool
    {
        $user = $request->user();

        if ($user === null) {
            return true;
        }

        return (bool) $user->canAccessTenant($tenant);
    }
}
