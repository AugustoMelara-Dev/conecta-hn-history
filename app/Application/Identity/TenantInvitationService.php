<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TenantInvitationService
{
    public function canManageTeam(string $tenantId, string $userId): bool
    {
        try {
            $this->authorizeInviter($tenantId, $userId);

            return true;
        } catch (DomainException) {
            return false;
        }
    }

    /**
     * @param  list<string>  $grantedPermissions
     */
    public function issue(
        string $tenantId,
        string $inviterUserId,
        string $email,
        string $role,
        ?string $presetKey = null,
        array $grantedPermissions = [],
    ): IssuedTenantInvitation {
        $email = $this->normalizedEmail($email);
        $role = $this->validatedRole($role);
        $permissions = $this->validatedPermissions($grantedPermissions);
        $inviterRole = $this->authorizeInviter($tenantId, $inviterUserId);
        $this->authorizeDelegation(
            tenantId: $tenantId,
            inviterUserId: $inviterUserId,
            inviterRole: $inviterRole,
            invitedRole: Role::from($role),
            permissions: $permissions,
        );

        return DB::transaction(function () use (
            $tenantId,
            $inviterUserId,
            $email,
            $role,
            $presetKey,
            $permissions,
        ): IssuedTenantInvitation {
            $now = CarbonImmutable::now();
            $activeInvitation = DB::table('tenant_invitations')
                ->where('tenant_id', $tenantId)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->lockForUpdate()
                ->exists();

            if ($activeInvitation) {
                throw new DomainException('An active invitation already exists for this identity.');
            }

            $id = (string) Str::uuid();
            $plainTextToken = bin2hex(random_bytes(32));
            $expiresAt = $now->addDays(7);
            $this->preparePendingMembership(
                tenantId: $tenantId,
                email: $email,
                role: $role,
                presetKey: $presetKey,
                permissions: $permissions,
                now: $now,
            );

            DB::table('tenant_invitations')->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'email' => $email,
                'role' => $role,
                'preset_key' => $presetKey,
                'granted_permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'token_hash' => hash('sha256', $plainTextToken),
                'invited_by_user_id' => $inviterUserId,
                'expires_at' => $expiresAt,
                'accepted_at' => null,
                'accepted_by_user_id' => null,
                'revoked_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->auditInvitation(
                action: 'issued',
                tenantId: $tenantId,
                actorUserId: $inviterUserId,
                invitationId: $id,
                email: $email,
                role: $role,
            );

            return new IssuedTenantInvitation($id, $email, $plainTextToken, $expiresAt);
        });
    }

    public function accept(string $plainTextToken, string $authenticatedUserId): string
    {
        if ($plainTextToken === '') {
            throw new InvalidArgumentException('Invitation token is required.');
        }

        return DB::transaction(function () use ($plainTextToken, $authenticatedUserId): string {
            $now = CarbonImmutable::now();
            $invitation = DB::table('tenant_invitations')
                ->where('token_hash', hash('sha256', $plainTextToken))
                ->lockForUpdate()
                ->first();

            if ($invitation === null
                || $invitation->accepted_at !== null
                || $invitation->revoked_at !== null
                || CarbonImmutable::parse($invitation->expires_at)->lessThanOrEqualTo($now)) {
                throw new DomainException('Invitation is no longer active.');
            }

            $userEmail = DB::table('users')->where('id', $authenticatedUserId)->value('email');
            if (! is_string($userEmail) || $this->normalizedEmail($userEmail) !== $invitation->email) {
                throw new DomainException('Invitation identity does not match the authenticated user.');
            }

            $existing = DB::table('tenant_memberships')
                ->where('tenant_id', $invitation->tenant_id)
                ->where('user_id', $authenticatedUserId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->status !== 'pending') {
                throw new DomainException('Membership is not eligible for invitation acceptance.');
            }

            $membershipId = $existing?->id ?? (string) Str::uuid();
            $values = [
                'role' => $invitation->role,
                'preset_key' => $invitation->preset_key,
                'granted_permissions' => $invitation->granted_permissions,
                'status' => 'active',
                'invited_at' => $invitation->created_at,
                'accepted_at' => $now,
                'suspended_at' => null,
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'updated_at' => $now,
            ];

            if ($existing === null) {
                DB::table('tenant_memberships')->insert($values + [
                    'id' => $membershipId,
                    'tenant_id' => $invitation->tenant_id,
                    'user_id' => $authenticatedUserId,
                    'created_at' => $now,
                ]);
            } else {
                DB::table('tenant_memberships')->where('id', $membershipId)->update($values);
            }

            DB::table('tenant_invitations')->where('id', $invitation->id)->update([
                'accepted_at' => $now,
                'accepted_by_user_id' => $authenticatedUserId,
                'updated_at' => $now,
            ]);
            $this->auditInvitation(
                action: 'accepted',
                tenantId: (string) $invitation->tenant_id,
                actorUserId: $authenticatedUserId,
                invitationId: (string) $invitation->id,
                email: (string) $invitation->email,
                role: (string) $invitation->role,
            );

            return $membershipId;
        });
    }

    public function preview(string $plainTextToken, string $authenticatedUserId): TenantInvitationPreview
    {
        $preview = $this->publicPreview($plainTextToken);
        $userEmail = DB::table('users')->where('id', $authenticatedUserId)->value('email');
        if (! is_string($userEmail) || $this->normalizedEmail($userEmail) !== $preview->email) {
            throw new DomainException('Invitation identity does not match the authenticated user.');
        }

        return $preview;
    }

    public function publicPreview(string $plainTextToken): TenantInvitationPreview
    {
        if ($plainTextToken === '') {
            throw new InvalidArgumentException('Invitation token is required.');
        }

        $invitation = DB::table('tenant_invitations')
            ->join('tenants', 'tenants.id', '=', 'tenant_invitations.tenant_id')
            ->join('users as inviters', 'inviters.id', '=', 'tenant_invitations.invited_by_user_id')
            ->where('tenant_invitations.token_hash', hash('sha256', $plainTextToken))
            ->select([
                'tenant_invitations.email',
                'tenant_invitations.role',
                'tenant_invitations.expires_at',
                'tenant_invitations.accepted_at',
                'tenant_invitations.revoked_at',
                'tenants.id as tenant_id',
                'tenants.name as tenant_name',
                'tenants.slug as tenant_slug',
                'inviters.name as inviter_name',
            ])
            ->first();

        if ($invitation === null
            || $invitation->accepted_at !== null
            || $invitation->revoked_at !== null
            || CarbonImmutable::parse($invitation->expires_at)->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw new DomainException('Invitation is no longer active.');
        }

        return new TenantInvitationPreview(
            tenantId: (string) $invitation->tenant_id,
            tenantName: (string) $invitation->tenant_name,
            tenantSlug: (string) $invitation->tenant_slug,
            inviterName: (string) $invitation->inviter_name,
            email: (string) $invitation->email,
            role: (string) $invitation->role,
            expiresAt: CarbonImmutable::parse($invitation->expires_at),
        );
    }

    public function revoke(string $invitationId, string $actorUserId): void
    {
        DB::transaction(function () use ($invitationId, $actorUserId): void {
            $invitation = DB::table('tenant_invitations')
                ->where('id', $invitationId)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new DomainException('Invitation is no longer active.');
            }

            $this->authorizeInviter((string) $invitation->tenant_id, $actorUserId);

            if ($invitation->accepted_at !== null) {
                throw new DomainException('Accepted invitation cannot be revoked.');
            }

            if ($invitation->revoked_at !== null) {
                return;
            }

            DB::table('tenant_invitations')->where('id', $invitationId)->update([
                'revoked_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ]);
            $this->auditInvitation(
                action: 'revoked',
                tenantId: (string) $invitation->tenant_id,
                actorUserId: $actorUserId,
                invitationId: $invitationId,
                email: (string) $invitation->email,
                role: (string) $invitation->role,
            );

            $invitedUserId = DB::table('users')->where('email', $invitation->email)->value('id');
            if (is_string($invitedUserId)) {
                DB::table('tenant_memberships')
                    ->where('tenant_id', $invitation->tenant_id)
                    ->where('user_id', $invitedUserId)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => CarbonImmutable::now(),
                        'revoked_by_user_id' => $actorUserId,
                        'updated_at' => CarbonImmutable::now(),
                    ]);
            }
        });
    }

    public function reissue(string $invitationId, string $actorUserId): IssuedTenantInvitation
    {
        return DB::transaction(function () use ($invitationId, $actorUserId): IssuedTenantInvitation {
            $invitation = DB::table('tenant_invitations')
                ->where('id', $invitationId)
                ->lockForUpdate()
                ->first();

            if ($invitation === null
                || $invitation->accepted_at !== null
                || $invitation->revoked_at !== null) {
                throw new DomainException('Invitation is no longer eligible for reissue.');
            }

            $this->authorizeInviter((string) $invitation->tenant_id, $actorUserId);
            $now = CarbonImmutable::now();
            DB::table('tenant_invitations')->where('id', $invitationId)->update([
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);
            $this->auditInvitation(
                action: 'superseded',
                tenantId: (string) $invitation->tenant_id,
                actorUserId: $actorUserId,
                invitationId: $invitationId,
                email: (string) $invitation->email,
                role: (string) $invitation->role,
            );

            return $this->issue(
                tenantId: (string) $invitation->tenant_id,
                inviterUserId: $actorUserId,
                email: (string) $invitation->email,
                role: (string) $invitation->role,
                presetKey: $invitation->preset_key !== null ? (string) $invitation->preset_key : null,
                grantedPermissions: $this->decodedPermissions($invitation->granted_permissions),
            );
        });
    }

    private function authorizeInviter(string $tenantId, string $inviterUserId): Role
    {
        $isOwner = DB::table('tenants')
            ->where('id', $tenantId)
            ->where('user_id', $inviterUserId)
            ->exists();
        if ($isOwner) {
            return Role::OWNER;
        }

        $membershipRole = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $inviterUserId)
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->whereIn('role', [Role::OWNER->value, Role::ADMIN->value])
            ->value('role');

        $role = is_string($membershipRole) ? Role::tryFrom($membershipRole) : null;
        if (! $role instanceof Role) {
            throw new DomainException('Inviter is not authorized to manage this tenant team.');
        }

        return $role;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function authorizeDelegation(
        string $tenantId,
        string $inviterUserId,
        Role $inviterRole,
        Role $invitedRole,
        array $permissions,
    ): void {
        if ($inviterRole === Role::OWNER) {
            return;
        }

        if ($this->roleRank($invitedRole) >= $this->roleRank($inviterRole)) {
            throw new DomainException('Inviter may only delegate a less privileged role.');
        }

        $rolePermissions = array_values(array_filter(
            (array) config("rbac.roles.{$inviterRole->value}", []),
            static fn (mixed $permission): bool => is_string($permission),
        ));
        $extraPermissions = $this->decodedPermissions(
            DB::table('tenant_memberships')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $inviterUserId)
                ->value('granted_permissions'),
        );
        $delegable = array_values(array_unique([...$rolePermissions, ...$extraPermissions]));

        foreach ($permissions as $permission) {
            if (! in_array($permission, $delegable, true)) {
                throw new DomainException("Inviter cannot delegate permission [{$permission}].");
            }
        }
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

    /**
     * @param  list<string>  $permissions
     */
    private function preparePendingMembership(
        string $tenantId,
        string $email,
        string $role,
        ?string $presetKey,
        array $permissions,
        CarbonImmutable $now,
    ): void {
        $userId = DB::table('users')->where('email', $email)->value('id');

        if (! is_string($userId)) {
            return;
        }

        $membership = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($membership !== null && $membership->status !== 'pending') {
            throw new DomainException('Identity already has a non-pending tenant membership.');
        }

        $values = [
            'role' => $role,
            'preset_key' => $presetKey,
            'granted_permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'invited_at' => $now,
            'accepted_at' => null,
            'suspended_at' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'updated_at' => $now,
        ];

        if ($membership === null) {
            DB::table('tenant_memberships')->insert($values + [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'created_at' => $now,
            ]);

            return;
        }

        DB::table('tenant_memberships')->where('id', $membership->id)->update($values);
    }

    private function normalizedEmail(string $email): string
    {
        $normalized = mb_strtolower(trim($email));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid invitation email is required.');
        }

        return $normalized;
    }

    private function validatedRole(string $role): string
    {
        $parsed = Role::tryFrom($role);

        if ($parsed === null || $parsed === Role::OWNER) {
            throw new InvalidArgumentException('Invitation role is not allowed.');
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
        $allowedPermissions = array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::cases(),
        );

        foreach ($normalized as $permission) {
            if (! in_array($permission, $allowedPermissions, true)) {
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

    private function auditInvitation(
        string $action,
        string $tenantId,
        string $actorUserId,
        string $invitationId,
        string $email,
        string $role,
    ): void {
        activity('tenant-governance')
            ->causedBy($actorUserId)
            ->withProperties([
                'event_type' => "tenant.invitation.{$action}",
                'tenant_id' => $tenantId,
                'actor_user_id' => $actorUserId,
                'invitation_id' => $invitationId,
                'identity_hash' => hash('sha256', $this->normalizedEmail($email)),
                'role' => $role,
            ])
            ->event("tenant.invitation.{$action}")
            ->log("tenant_invitation_{$action}");
    }
}
