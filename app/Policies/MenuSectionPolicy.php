<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\MenuSection;
use App\Models\User;

class MenuSectionPolicy extends TenantAwarePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, Capability::MENU_MODIFIERS, Permission::MENU_VIEW);
    }

    public function view(User $user, MenuSection $menuSection): bool
    {
        return $this->allows($user, Capability::MENU_MODIFIERS, Permission::MENU_VIEW, $menuSection);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Capability::MENU_MODIFIERS, Permission::MENU_MANAGE);
    }

    public function update(User $user, MenuSection $menuSection): bool
    {
        return $this->allows($user, Capability::MENU_MODIFIERS, Permission::MENU_MANAGE, $menuSection);
    }

    public function delete(User $user, MenuSection $menuSection): bool
    {
        return $this->allows($user, Capability::MENU_MODIFIERS, Permission::MENU_MANAGE, $menuSection);
    }

    public function restore(User $user, MenuSection $menuSection): bool
    {
        return $this->allows($user, Capability::MENU_MODIFIERS, Permission::MENU_MANAGE, $menuSection);
    }

    public function forceDelete(User $user, MenuSection $menuSection): bool
    {
        return $this->allows($user, Capability::MENU_MODIFIERS, Permission::MENU_MANAGE, $menuSection)
            && $this->isTenantOwner($user, $this->resolveTenant($user, $menuSection));
    }
}
