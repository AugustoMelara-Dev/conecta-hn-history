<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MenuSection;
use App\Models\User;

/**
 * MenuSectionPolicy - Tenant-Aware Authorization for Menu Sections
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * SECURITY: Enforces tenant ownership for menu organization
 * ══════════════════════════════════════════════════════════════════════════════
 */
class MenuSectionPolicy extends TenantAwarePolicy
{
    /**
     * Determine whether the user can view any menu sections.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can view the menu section.
     */
    public function view(User $user, MenuSection $menuSection): bool
    {
        return $this->ownsResource($user, $menuSection);
    }

    /**
     * Determine whether the user can create menu sections.
     */
    public function create(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can update the menu section.
     */
    public function update(User $user, MenuSection $menuSection): bool
    {
        return $this->ownsResource($user, $menuSection);
    }

    /**
     * Determine whether the user can delete the menu section.
     */
    public function delete(User $user, MenuSection $menuSection): bool
    {
        return $this->ownsResource($user, $menuSection);
    }

    /**
     * Determine whether the user can restore the menu section.
     */
    public function restore(User $user, MenuSection $menuSection): bool
    {
        return $this->ownsResource($user, $menuSection);
    }

    /**
     * Determine whether the user can permanently delete the menu section.
     */
    public function forceDelete(User $user, MenuSection $menuSection): bool
    {
        return $this->ownsResource($user, $menuSection) && $this->isAdmin($user);
    }
}
