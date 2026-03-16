<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

class CampaignPolicy
{
    public function __construct(private TenantPermissionGuardInterface $guard) {}

    public function viewAny(User $user): bool
    {
        return $this->tenant() instanceof Tenant
            && $this->guard->allows($user, $this->tenant(), Capability::NOTIFICATIONS_BASIC, Permission::MARKETING_VIEW);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->tenant() instanceof Tenant
            && $this->guard->allows($user, $this->tenant(), Capability::NOTIFICATIONS_BASIC, Permission::MARKETING_MANAGE);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->create($user);
    }

    private function tenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
