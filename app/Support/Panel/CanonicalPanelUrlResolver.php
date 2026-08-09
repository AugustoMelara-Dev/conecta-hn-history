<?php

declare(strict_types=1);

namespace App\Support\Panel;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class CanonicalPanelUrlResolver
{
    public function forUser(?User $user, string $section = 'dashboard', array $parameters = []): string
    {
        if (! $user) {
            return route('tenant.register', absolute: false);
        }

        if ($user->isSuperAdmin()) {
            if ((bool) config('security.admin_mfa_required', false) && ! $user->hasEnabledTwoFactorAuthentication()) {
                return route('profile.two-factor.show', absolute: false);
            }

            return '/admin';
        }

        $tenant = $this->resolveTenantForUser($user);

        if (! $tenant) {
            return route('tenant.register', absolute: false);
        }

        return $this->forTenant($tenant, $section, $parameters);
    }

    public function forTenant(Tenant $tenant, string $section = 'dashboard', array $parameters = []): string
    {
        $baseUrl = '/app/'.rawurlencode((string) $tenant->slug);

        return match ($section) {
            'products' => $baseUrl.'/products',
            'products.create' => $baseUrl.'/products/create',
            'products.edit' => $baseUrl.'/products/'.rawurlencode((string) ($parameters['product'] ?? '')).'/edit',
            'catalog.imports' => '/workspace/catalog-imports',
            'orders' => $baseUrl.'/orders',
            'billing' => $baseUrl.'/manage-billing',
            'settings' => $baseUrl.'/settings',
            'marketing' => $baseUrl.'/manage-marketing',
            'reports' => $baseUrl.'/reports-overview',
            'upgrade' => $baseUrl.'/upgrade-to-pro',
            default => $baseUrl,
        };
    }

    private function resolveTenantForUser(User $user): ?Tenant
    {
        if (app()->has('currentTenant')) {
            $currentTenant = app('currentTenant');

            if ($currentTenant instanceof Tenant && $currentTenant->exists) {
                return $currentTenant;
            }
        }

        if ($user->relationLoaded('tenant') && $user->tenant instanceof Tenant) {
            return $user->tenant;
        }

        $primaryTenant = $user->tenant()->first();

        if ($primaryTenant instanceof Tenant) {
            return $primaryTenant;
        }

        $ownedTenant = $user->tenants()
            ->orderBy('name')
            ->first();

        if ($ownedTenant instanceof Tenant) {
            return $ownedTenant;
        }

        if (! Schema::hasTable('tenant_memberships')) {
            return null;
        }

        $memberTenant = $user->memberTenants()
            ->wherePivotNotNull('accepted_at')
            ->orderBy('name')
            ->first();

        return $memberTenant instanceof Tenant ? $memberTenant : null;
    }
}
