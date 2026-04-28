<?php

declare(strict_types=1);

namespace App\Infrastructure\Scope;

use App\Contracts\Scope\TenantContextInterface;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * FilamentTenantContext - Infrastructure adapter for Filament tenant panels.
 *
 * This is the only adapter allowed to touch Filament's runtime tenant facade
 * directly. Everyone else should depend on TenantContextInterface.
 */
final class FilamentTenantContext implements TenantContextInterface
{
    public function getTenantUuid(): ?string
    {
        $tenant = $this->resolveTenant();

        return $tenant !== null ? (string) $tenant->getKey() : null;
    }

    private function resolveTenant(): ?Tenant
    {
        return $this->resolveFilamentTenant() ?? $this->resolveAuthenticatedTenant();
    }

    private function resolveFilamentTenant(): ?Tenant
    {
        if (! class_exists(Filament::class)) {
            return null;
        }

        try {
            $tenant = Filament::getTenant();
        } catch (\Throwable) {
            return null;
        }

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function resolveAuthenticatedTenant(): ?Tenant
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $primaryTenant = $user->tenants()->first();

        return $primaryTenant instanceof Tenant ? $primaryTenant : null;
    }
}
