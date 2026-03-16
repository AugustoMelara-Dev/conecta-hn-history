<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Location;
use App\Models\User;

class LocationPolicy extends TenantAwarePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::SETTINGS_VIEW);
    }

    public function view(User $user, Location $location): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::SETTINGS_VIEW, $location);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::SETTINGS_UPDATE);
    }

    public function update(User $user, Location $location): bool
    {
        return $this->allows($user, Capability::CATALOG_BASIC, Permission::SETTINGS_UPDATE, $location);
    }

    public function delete(User $user, Location $location): bool
    {
        if ($location->is_primary) {
            return false;
        }

        return $this->allows($user, Capability::CATALOG_BASIC, Permission::SETTINGS_UPDATE, $location);
    }
}
