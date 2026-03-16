<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementPolicy extends TenantAwarePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, Capability::INVENTORY_TRACKING, Permission::INVENTORY_VIEW);
    }

    public function view(User $user, InventoryMovement $inventoryMovement): bool
    {
        return $this->allows($user, Capability::INVENTORY_TRACKING, Permission::INVENTORY_VIEW, $inventoryMovement);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }

    public function delete(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }
}
