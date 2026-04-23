<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\Tenant;
use App\Models\User;

final class AuthRedirector
{
    public const SURFACE_BUSINESS_APP = 'business_app';

    public const SURFACE_USER_APP = 'user_app';

    public const SURFACE_ADMIN_APP = 'admin_app';

    public const AUDIENCE_OWNER = 'owner';

    public const AUDIENCE_STAFF = 'staff';

    public const AUDIENCE_CUSTOMER = 'customer';

    public const AUDIENCE_ADMIN = 'admin';

    public static function isDirectorySurfaceUser(?object $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return false;
        }

        return self::resolveTenantSlug($user) === null;
    }

    public static function isAppSurfaceUser(?object $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return false;
        }

        return self::resolveTenantSlug($user) !== null;
    }

    public static function destinationFor(?object $user, bool $appendVerifiedFlag = false): string
    {
        $suffix = $appendVerifiedFlag ? '?verified=1' : '';

        if (! $user instanceof User) {
            return self::directoryDestination().$suffix;
        }

        if (! $user->hasVerifiedEmail()) {
            return route('verification.notice', [], false);
        }

        if ($user->isSuperAdmin()) {
            return self::adminPanelDestinationFor($user).$suffix;
        }

        // Business context wins over legacy role strings to avoid sending
        // owner/staff accounts with accepted tenant context to the B2C surface.
        if (self::isAppSurfaceUser($user)) {
            return self::appPanelDestinationFor($user).$suffix;
        }

        return self::directoryDestination().$suffix;
    }

    public static function appPanelDestinationFor(?object $user): string
    {
        if (! $user instanceof User) {
            return self::directoryDestination();
        }

        $tenantSlug = self::resolveTenantSlug($user);

        if ($tenantSlug !== null) {
            $path = route('filament.app.pages.dashboard', ['tenant' => $tenantSlug], false);

            return self::surfaceUrl($path, config('app.platform_app_url'));
        }

        return self::directoryDestination();
    }

    public static function adminPanelDestinationFor(?object $user): string
    {
        if (! $user instanceof User) {
            return self::directoryDestination();
        }

        $adminOrigin = config('app.platform_admin_url', config('app.platform_app_url'));

        return self::surfaceUrl(route('filament.admin.pages.dashboard', [], false), $adminOrigin);
    }

    private static function resolveTenantSlug(User $user, bool $allowAnyTenant = false): ?string
    {
        $primaryTenant = $user->tenant;

        if ($primaryTenant instanceof Tenant && filled($primaryTenant->slug)) {
            return (string) $primaryTenant->slug;
        }

        $memberTenant = $user->memberTenants()
            ->wherePivotNotNull('accepted_at')
            ->select('tenants.*')
            ->first();

        $memberTenantSlug = data_get($memberTenant, 'slug');

        if (is_string($memberTenantSlug) && $memberTenantSlug !== '') {
            return $memberTenantSlug;
        }

        if ($allowAnyTenant) {
            $fallbackTenant = Tenant::query()
                ->whereNotNull('slug')
                ->orderBy('name')
                ->first();

            if ($fallbackTenant instanceof Tenant && filled($fallbackTenant->slug)) {
                return (string) $fallbackTenant->slug;
            }
        }

        return null;
    }

    public static function profileDestinationFor(?object $user): string
    {
        if (! $user instanceof User) {
            return self::directoryDestination();
        }

        if (! $user->hasVerifiedEmail()) {
            return route('verification.notice', [], false);
        }

        if ($user->isSuperAdmin()) {
            $adminOrigin = config('app.platform_admin_url', config('app.platform_app_url'));

            return self::surfaceUrl(route('filament.admin.auth.profile', [], false), $adminOrigin);
        }

        // Mirror destinationFor(): tenant context must route to B2B profile.
        if (self::resolveTenantSlug($user) !== null) {
            return route('filament.app.auth.profile', [], false);
        }

        return self::directoryDestination();
    }

    public static function hasBusinessContext(?object $user): bool
    {
        return $user instanceof User && self::resolveTenantSlug($user) !== null;
    }

    public static function homeSurfaceFor(?object $user): string
    {
        if (! $user instanceof User) {
            return self::SURFACE_USER_APP;
        }

        if ($user->isSuperAdmin()) {
            return self::SURFACE_ADMIN_APP;
        }

        if (self::hasBusinessContext($user)) {
            return self::SURFACE_BUSINESS_APP;
        }

        return self::SURFACE_USER_APP;
    }

    public static function audienceFor(?object $user): string
    {
        if (! $user instanceof User) {
            return self::AUDIENCE_CUSTOMER;
        }

        if ($user->isSuperAdmin()) {
            return self::AUDIENCE_ADMIN;
        }

        if ($user->isStaff()) {
            return self::AUDIENCE_STAFF;
        }

        if (self::hasBusinessContext($user)) {
            return self::AUDIENCE_OWNER;
        }

        return self::AUDIENCE_CUSTOMER;
    }

    /**
     * @return array{redirect_to: string, home_surface: string, audience: string}
     */
    public static function contextFor(?object $user): array
    {
        return [
            'redirect_to' => self::destinationFor($user),
            'home_surface' => self::homeSurfaceFor($user),
            'audience' => self::audienceFor($user),
        ];
    }

    private static function directoryDestination(): string
    {
        return self::surfaceUrl(route('directory.index', [], false), config('app.platform_directory_url'));
    }

    private static function surfaceUrl(string $path, mixed $baseUrl): string
    {
        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            return $path;
        }

        $origin = rtrim($baseUrl, '/');
        $normalizedPath = '/'.ltrim($path, '/');

        return $origin.$normalizedPath;
    }
}
