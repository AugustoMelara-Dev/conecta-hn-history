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
use Illuminate\Support\Facades\Schema;

final class StandardTenantPermissionGuard implements TenantPermissionGuardInterface
{
    public function allows(object $user, object $tenant, Capability $capability, Permission $permission): bool
    {
        if (! $user instanceof User || ! $tenant instanceof Tenant) {
            return false;
        }

        if (! $tenant->hasCapability($capability)) {
            return false;
        }

        if ((string) $tenant->user_id === (string) $user->id) {
            return true;
        }

        if (! Schema::hasTable('tenant_memberships')) {
            return false;
        }

        $membership = $tenant->memberships()
            ->whereNotNull('accepted_at')
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
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

            if (! $role instanceof Role) {
                return false;
            }

            /** @var array<int, string> $rolePermissions */
            $rolePermissions = array_values(array_filter(
                (array) config("rbac.roles.{$role->value}", []),
                static fn (mixed $value): bool => is_string($value) && $value !== ''
            ));

            $membershipPermissions = $membership->extraPermissions();

            return in_array(
                $permission->value,
                array_values(array_unique(array_merge($rolePermissions, $membershipPermissions))),
                true
            );
        }

        return false;
    }
}
