<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\TenantMembership;
use App\Models\User;

class TenantMembershipPolicy extends TenantAwarePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, Capability::STAFF_BASIC, Permission::STAFF_VIEW);
    }

    public function view(User $user, TenantMembership $membership): bool
    {
        return $this->allows($user, Capability::STAFF_BASIC, Permission::STAFF_VIEW, $membership);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Capability::STAFF_BASIC, Permission::STAFF_MANAGE);
    }

    public function update(User $user, TenantMembership $membership): bool
    {
        if ($membership->isOwner() && ! $this->isTenantOwner($user, $this->resolveTenant($user, $membership))) {
            return false;
        }

        return $this->allows($user, Capability::STAFF_BASIC, Permission::STAFF_ASSIGN_ROLES, $membership);
    }

    public function delete(User $user, TenantMembership $membership): bool
    {
        if ($membership->isOwner()) {
            return false;
        }

        return $this->allows($user, Capability::STAFF_BASIC, Permission::STAFF_ASSIGN_ROLES, $membership);
    }

    public function restore(User $user, TenantMembership $membership): bool
    {
        return $this->update($user, $membership);
    }

    public function forceDelete(User $user, TenantMembership $membership): bool
    {
        return $this->delete($user, $membership) && $this->isTenantOwner($user, $this->resolveTenant($user, $membership));
    }
}
