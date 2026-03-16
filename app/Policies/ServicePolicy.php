<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

class ServicePolicy
{
    public function __construct(private TenantPermissionGuardInterface $guard) {}

    public function viewAny(User $user): bool
    {
        return $this->tenant() instanceof Tenant
            && $this->guard->allows($user, $this->tenant(), Capability::SERVICE_PACKAGES, Permission::CATALOG_VIEW);
    }

    public function view(User $user, Service $service): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->tenant() instanceof Tenant
            && $this->guard->allows($user, $this->tenant(), Capability::SERVICE_PACKAGES, Permission::CATALOG_CREATE);
    }

    public function update(User $user, Service $service): bool
    {
        return $this->tenant() instanceof Tenant
            && $this->guard->allows($user, $this->tenant(), Capability::SERVICE_PACKAGES, Permission::CATALOG_UPDATE);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->tenant() instanceof Tenant
            && $this->guard->allows($user, $this->tenant(), Capability::SERVICE_PACKAGES, Permission::CATALOG_DELETE);
    }

    private function tenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
