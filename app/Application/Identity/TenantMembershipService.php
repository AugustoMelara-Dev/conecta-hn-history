<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TenantMembershipService
{
    /**
     * @param  list<string>  $grantedPermissions
     */
    public function update(
        string $membershipId,
        string $actorUserId,
        string $role,
        ?string $presetKey,
        array $grantedPermissions,
    ): void {
        $role = $this->validatedRole($role);
        $permissions = $this->validatedPermissions($grantedPermissions);

        DB::transaction(function () use (
            $membershipId,
            $actorUserId,
            $role,
            $presetKey,
            $permissions,
        ): void {
            $membership = $this->lockedMembership($membershipId);
            $actorRole = $this->authorizeActorForTarget($membership, $actorUserId);
            $this->authorizeDelegation(
                tenantId: (string) $membership->tenant_id,
                actorUserId: $actorUserId,
                actorRole: $actorRole,
                delegatedRole: Role::from($role),
                permissions: $permissions,
            );

            if ($membership->status !== 'active') {
                throw new DomainException('Only an active membership can be updated.');
            }

            DB::table('tenant_memberships')->where('id', $membershipId)->update([
                'role' => $role,
                'preset_key' => $presetKey,
                'granted_permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => CarbonImmutable::now(),
            ]);
            $this->revokeSessions((string) $membership->user_id, 'tenant_membership_privileges_changed');
            $this->revokeTenantTokens((string) $membership->user_id, (string) $membership->tenant_id);
            $this->audit('updated', $membership, $actorUserId, $role);
        });
    }

    public function suspend(string $membershipId, string $actorUserId): void
    {
        DB::transaction(function () use ($membershipId, $actorUserId): void {
            $membership = $this->lockedMembership($membershipId);
            $this->authorizeActorForTarget($membership, $actorUserId);

            if ($membership->status === 'suspended') {
                return;
            }

            if ($membership->status !== 'active') {
                throw new DomainException('Only an active membership can be suspended.');
            }

            $now = CarbonImmutable::now();
            DB::table('tenant_memberships')->where('id', $membershipId)->update([
                'status' => 'suspended',
                'suspended_at' => $now,
                'updated_at' => $now,
            ]);
            $this->revokeSessions((string) $membership->user_id, 'tenant_membership_suspended');
            $this->revokeTenantTokens((string) $membership->user_id, (string) $membership->tenant_id);
            $this->audit('suspended', $membership, $actorUserId, (string) $membership->role);
        });
    }

    public function reactivate(string $membershipId, string $actorUserId): void
    {
        DB::transaction(function () use ($membershipId, $actorUserId): void {
            $membership = $this->lockedMembership($membershipId);
            $this->authorizeActorForTarget($membership, $actorUserId);

            if ($membership->status === 'active') {
                return;
            }

            if ($membership->status !== 'suspended') {
                throw new DomainException('Only a suspended membership can be reactivated.');
            }

            DB::table('tenant_memberships')->where('id', $membershipId)->update([
                'status' => 'active',
                'suspended_at' => null,
                'updated_at' => CarbonImmutable::now(),
            ]);
            $this->audit('reactivated', $membership, $actorUserId, (string) $membership->role);
        });
    }

    public function revoke(string $membershipId, string $actorUserId): void
    {
        DB::transaction(function () use ($membershipId, $actorUserId): void {
            $membership = $this->lockedMembership($membershipId);
            $this->authorizeActorForTarget($membership, $actorUserId);

            if ($membership->status === 'revoked') {
                return;
            }

            $now = CarbonImmutable::now();
            DB::table('tenant_memberships')->where('id', $membershipId)->update([
                'status' => 'revoked',
                'suspended_at' => null,
                'revoked_at' => $now,
                'revoked_by_user_id' => $actorUserId,
                'updated_at' => $now,
            ]);
            $this->revokeSessions((string) $membership->user_id, 'tenant_membership_revoked');
            $this->revokeTenantTokens((string) $membership->user_id, (string) $membership->tenant_id);
            $this->audit('revoked', $membership, $actorUserId, (string) $membership->role);
        });
    }

    private function lockedMembership(string $membershipId): object
    {
        $membership = DB::table('tenant_memberships')
            ->where('id', $membershipId)
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            throw new DomainException('Tenant membership was not found.');
        }

        return $membership;
    }

    private function authorizeActorForTarget(object $membership, string $actorUserId): Role
    {
        $targetRole = Role::tryFrom((string) $membership->role);
        if ($targetRole === Role::OWNER) {
            throw new DomainException('The owner membership cannot be changed.');
        }

        $actorRole = $this->actorRole((string) $membership->tenant_id, $actorUserId);
        if ($actorRole !== Role::OWNER
            && (! $targetRole instanceof Role || $this->roleRank($targetRole) >= $this->roleRank($actorRole))) {
            throw new DomainException('Actor may only manage a less privileged membership.');
        }

        return $actorRole;
    }

    private function actorRole(string $tenantId, string $actorUserId): Role
    {
        if (DB::table('tenants')
            ->where('id', $tenantId)
            ->where('user_id', $actorUserId)
            ->exists()) {
            return Role::OWNER;
        }

        $role = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $actorUserId)
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->value('role');
        $parsed = is_string($role) ? Role::tryFrom($role) : null;

        if (! in_array($parsed, [Role::OWNER, Role::ADMIN], true)) {
            throw new DomainException('Actor is not authorized to manage this tenant team.');
        }

        return $parsed;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function authorizeDelegation(
        string $tenantId,
        string $actorUserId,
        Role $actorRole,
        Role $delegatedRole,
        array $permissions,
    ): void {
        if ($actorRole === Role::OWNER) {
            return;
        }

        if ($this->roleRank($delegatedRole) >= $this->roleRank($actorRole)) {
            throw new DomainException('Actor may only delegate a less privileged role.');
        }

        $rolePermissions = array_values(array_filter(
            (array) config("rbac.roles.{$actorRole->value}", []),
            static fn (mixed $permission): bool => is_string($permission),
        ));
        $extraPermissions = $this->decodedPermissions(
            DB::table('tenant_memberships')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $actorUserId)
                ->value('granted_permissions'),
        );
        $delegable = array_values(array_unique([...$rolePermissions, ...$extraPermissions]));

        foreach ($permissions as $permission) {
            if (! in_array($permission, $delegable, true)) {
                throw new DomainException("Actor cannot delegate permission [{$permission}].");
            }
        }
    }

    private function validatedRole(string $role): string
    {
        $parsed = Role::tryFrom($role);
        if ($parsed === null || $parsed === Role::OWNER) {
            throw new InvalidArgumentException('Membership role is not allowed.');
        }

        return $parsed->value;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function validatedPermissions(array $permissions): array
    {
        $normalized = array_values(array_unique($permissions));
        $allowed = array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::cases(),
        );

        foreach ($normalized as $permission) {
            if (! in_array($permission, $allowed, true)) {
                throw new InvalidArgumentException("Unknown tenant permission [{$permission}].");
            }
        }

        sort($normalized);

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function decodedPermissions(mixed $permissions): array
    {
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true, flags: JSON_THROW_ON_ERROR);
        }

        return array_values(array_filter(
            is_array($permissions) ? $permissions : [],
            static fn (mixed $permission): bool => is_string($permission),
        ));
    }

    private function roleRank(Role $role): int
    {
        return match ($role) {
            Role::OWNER => 5,
            Role::ADMIN => 4,
            Role::MANAGER => 3,
            Role::OPERATOR => 2,
            Role::VIEWER => 1,
        };
    }

    private function revokeSessions(string $userId, string $reason): void
    {
        DB::table('authenticated_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => CarbonImmutable::now(),
                'revocation_reason' => $reason,
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    private function revokeTenantTokens(string $userId, string $tenantId): void
    {
        DB::table('personal_access_tokens')
            ->whereIn('tokenable_type', ['user', 'App\\Models\\User'])
            ->where('tokenable_id', $userId)
            ->where('abilities', 'like', '%"tenant:'.$tenantId.'"%')
            ->delete();
    }

    private function audit(string $action, object $membership, string $actorUserId, string $role): void
    {
        activity('tenant-governance')
            ->causedBy($actorUserId)
            ->withProperties([
                'event_type' => "tenant.membership.{$action}",
                'tenant_id' => (string) $membership->tenant_id,
                'actor_user_id' => $actorUserId,
                'membership_id' => (string) $membership->id,
                'member_user_id' => (string) $membership->user_id,
                'role' => $role,
            ])
            ->event("tenant.membership.{$action}")
            ->log("tenant_membership_{$action}");
    }
}
