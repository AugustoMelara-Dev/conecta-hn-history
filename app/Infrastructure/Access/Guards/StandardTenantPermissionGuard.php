<?php

declare(strict_types=1);

namespace App\Infrastructure\Access\Guards;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

final class StandardTenantPermissionGuard implements TenantPermissionGuardInterface
{
    public function allows(User $user, Tenant $tenant, Capability $capability, Permission $permission): bool
    {
        if (! $tenant->hasCapability($capability)) {
            return false;
        }

        $membership = $tenant->memberships()
            ->whereNotNull('accepted_at')
            ->where('user_id', $user->id)
            ->first();

        if ($membership instanceof TenantMembership) {
            $roleValue = $membership->getAttribute('role');
            $role = $roleValue instanceof Role
                ? $roleValue
                : Role::tryFrom((string) $roleValue);

            if ($role === Role::OWNER) {
                return true;
            }

            if ($role === null) {
                return false;
            }

            $rolePermissions = config("rbac.roles.{$role->value}", []);

            return in_array($permission->value, $rolePermissions, true);
        }

        return (string) $tenant->user_id === (string) $user->id;
    }
}
