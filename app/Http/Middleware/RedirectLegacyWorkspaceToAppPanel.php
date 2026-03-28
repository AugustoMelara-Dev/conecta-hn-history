<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectLegacyWorkspaceToAppPanel
{
    /**
     * @var array<string, string>
     */
    private const ROUTE_MAP = [
        'tenant.dashboard' => 'filament.app.pages.dashboard',
        'tenant.products.index' => 'filament.app.resources.products.index',
        'tenant.orders.index' => 'filament.app.resources.orders.index',
        'tenant.marketing.index' => 'filament.app.pages.manage-marketing',
        'tenant.reports.index' => 'filament.app.pages.growth-center',
        'tenant.landing-page.index' => 'filament.app.tenant.profile',
        'tenant.billing.index' => 'filament.app.pages.manage-billing',
        'tenant.settings.index' => 'filament.app.pages.settings',
        'tenant.upgrade' => 'filament.app.pages.upgrade-to-pro',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || ! array_key_exists($routeName, self::ROUTE_MAP)) {
            return $next($request);
        }

        $tenantSlug = $this->resolveTenantSlug($request->user());

        if ($tenantSlug === null) {
            return $next($request);
        }

        return redirect()->route(self::ROUTE_MAP[$routeName], ['tenant' => $tenantSlug]);
    }

    private function resolveTenantSlug(?object $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        $primaryTenant = $user->tenant;

        if ($primaryTenant instanceof Tenant && filled($primaryTenant->slug)) {
            return (string) $primaryTenant->slug;
        }

        $memberTenant = $user->memberTenants()
            ->wherePivotNotNull('accepted_at')
            ->select('tenants.*')
            ->first();

        if ($memberTenant instanceof Tenant && filled($memberTenant->slug)) {
            return (string) $memberTenant->slug;
        }

        return null;
    }
}
