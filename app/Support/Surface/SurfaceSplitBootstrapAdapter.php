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
        $businessDashboard = $this->businessDashboardFor($user);
        $legacyPaths = [];

        foreach ($this->legacyPaths() as $legacyPath) {
            $legacyPaths[] = [
                'path' => $legacyPath,
                'status' => 'deprecated',
                'redirect_to' => $this->redirectTargetFor($legacyPath, $context['redirect_to'], $businessDashboard),
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

    private function redirectTargetFor(string $legacyPath, string $defaultTarget, ?string $businessDashboard): string
    {
        if (str_starts_with($legacyPath, '/workspace/') && is_string($businessDashboard) && $businessDashboard !== '') {
            return $businessDashboard;
        }

        return $defaultTarget;
    }

    private function businessDashboardFor(User $user): ?string
    {
        $tenantSlug = $this->tenantSlugFor($user);

        if ($tenantSlug === null) {
            return null;
        }

        return AuthRedirector::appPanelDestinationFor($user);
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
