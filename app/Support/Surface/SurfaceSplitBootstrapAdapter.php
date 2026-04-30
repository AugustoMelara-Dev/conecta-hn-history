<?php

declare(strict_types=1);

namespace App\Support\Surface;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Illuminate\Http\Request;

final class SurfaceSplitBootstrapAdapter
{
    /**
     * @var array<string, string>
     */
    private const BUSINESS_LEGACY_ROUTE_MAP = [
        '/dashboard' => 'filament.app.pages.dashboard',
        '/workspace/dashboard' => 'filament.app.pages.dashboard',
        '/workspace/products' => 'filament.app.resources.products.index',
        '/workspace/orders' => 'filament.app.resources.orders.index',
        '/workspace/marketing' => 'filament.app.pages.manage-marketing',
        '/workspace/reports' => 'filament.app.pages.growth-center',
        '/workspace/settings' => 'filament.app.pages.settings',
        '/workspace/billing' => 'filament.app.pages.manage-billing',
        '/workspace/upgrade' => 'filament.app.pages.upgrade-to-pro',
        '/workspace/landing-page' => 'filament.app.tenant.profile',
    ];

    /**
     * @return array{
     *     context: array{redirect_to: string, home_surface: string, audience: string},
     *     origins: array<string, string|null>,
     *     compatibility: array{
     *         migration_phase: string,
     *         legacy_paths: list<array{path: string, status: string, redirect_to: string}>
     *     }
     * }
     */
    public function forUser(User $user, ?Request $request = null): array
    {
        $context = AuthRedirector::contextFor($user);
        $tenantSlug = $this->tenantSlugFor($user);
        $legacyPaths = [];

        foreach ($this->legacyPaths() as $legacyPath) {
            $legacyPaths[] = [
                'path' => $legacyPath,
                'status' => 'deprecated',
                'redirect_to' => $this->redirectTargetFor($legacyPath, $context['redirect_to'], $tenantSlug),
            ];
        }

        return [
            'context' => $context,
            'origins' => $this->surfaceOrigins(),
            'compatibility' => [
                'migration_phase' => (string) config('surfaces.migration_phase', 'strangler_v1'),
                'legacy_paths' => $legacyPaths,
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function surfaceOrigins(): array
    {
        $origins = (array) config('surfaces.origins', []);

        return [
            'user_app' => $this->normalizeOrigin($origins['user_app'] ?? null),
            'business_app' => $this->normalizeOrigin($origins['business_app'] ?? null),
            'admin_app' => $this->normalizeOrigin($origins['admin_app'] ?? null),
            'platform_api' => $this->normalizeOrigin($origins['platform_api'] ?? null),
        ];
    }

    /**
     * @return list<string>
     */
    private function legacyPaths(): array
    {
        return array_values(array_map(
            static fn (mixed $path): string => (string) $path,
            (array) config('surfaces.legacy_paths', [])
        ));
    }

    private function redirectTargetFor(string $legacyPath, string $defaultTarget, ?string $tenantSlug): string
    {
        $businessRoute = self::BUSINESS_LEGACY_ROUTE_MAP[$legacyPath] ?? null;

        if ($businessRoute !== null && $tenantSlug !== null) {
            return route($businessRoute, ['tenant' => $tenantSlug], false);
        }

        return $defaultTarget;
    }

    private function tenantSlugFor(User $user): ?string
    {
        if ($user->tenant instanceof Tenant && filled($user->tenant->slug)) {
            return (string) $user->tenant->slug;
        }

        $memberTenant = $user->memberTenants()
            ->wherePivotNotNull('accepted_at')
            ->select('tenants.*')
            ->first();

        $memberTenantSlug = data_get($memberTenant, 'slug');

        return is_string($memberTenantSlug) && $memberTenantSlug !== '' ? $memberTenantSlug : null;
    }

    private function normalizeOrigin(mixed $origin): ?string
    {
        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        return rtrim($origin, '/');
    }
}
