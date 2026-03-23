<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\Tenant;
use App\Models\User;

final class AuthRedirector
{
    public static function destinationFor(?object $user, bool $appendVerifiedFlag = false): string
    {
        $suffix = $appendVerifiedFlag ? '?verified=1' : '';

        if (! $user instanceof User) {
            return route('dashboard', [], false).$suffix;
        }

        if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
            return route('verification.notice', [], false);
        }

        if ($user->isSuperAdmin()) {
            return self::adminPanelDestinationFor($user).$suffix;
        }

        $tenantSlug = self::resolveTenantSlug($user);

        if ($tenantSlug !== null) {
            return route('filament.app.pages.dashboard', ['tenant' => $tenantSlug], false).$suffix;
        }

        return route('dashboard', [], false).$suffix;
    }

    public static function appPanelDestinationFor(?object $user): string
    {
        if (! $user instanceof User) {
            return route('dashboard', [], false);
        }

        $tenantSlug = self::resolveTenantSlug($user);

        if ($tenantSlug !== null) {
            return route('filament.app.pages.dashboard', ['tenant' => $tenantSlug], false);
        }

        return route('dashboard', [], false);
    }

    public static function adminPanelDestinationFor(?object $user): string
    {
        if (! $user instanceof User) {
            return route('dashboard', [], false);
        }

        return route('filament.admin.pages.dashboard', [], false);
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

        if ($memberTenant instanceof Tenant && filled($memberTenant->slug)) {
            return (string) $memberTenant->slug;
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
            return route('dashboard', [], false);
        }

        if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
            return route('verification.notice', [], false);
        }

        if ($user->isSuperAdmin()) {
            return route('filament.admin.auth.profile', [], false);
        }

        if (self::resolveTenantSlug($user) !== null) {
            return route('filament.app.auth.profile', [], false);
        }

        return route('dashboard', [], false);
    }
}
