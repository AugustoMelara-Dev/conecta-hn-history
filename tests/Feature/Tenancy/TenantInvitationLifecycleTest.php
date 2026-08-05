<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Identity\TenantInvitationService;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class TenantInvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_persists_only_a_hash_and_accepts_exactly_once_for_the_matching_identity(): void
    {
        config(['activitylog.enabled' => true]);
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'member@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->create();
        DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->delete();

        $invitation = app(TenantInvitationService::class)->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: '  MEMBER@Example.Test ',
            role: 'operator',
            presetKey: 'operations',
            grantedPermissions: ['orders.manage', 'catalog.update'],
        );

        self::assertSame('member@example.test', $invitation->email);
        self::assertNotSame('', $invitation->plainTextToken);
        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->invitationId,
            'tenant_id' => $tenant->id,
            'email' => 'member@example.test',
            'role' => 'operator',
        ]);
        self::assertSame(
            hash('sha256', $invitation->plainTextToken),
            DB::table('tenant_invitations')->where('id', $invitation->invitationId)->value('token_hash'),
        );
        self::assertStringNotContainsString(
            $invitation->plainTextToken,
            json_encode(
                DB::table('tenant_invitations')->where('id', $invitation->invitationId)->first(),
                JSON_THROW_ON_ERROR,
            ),
        );
        $auditPayload = (string) DB::table('activity_log')
            ->where('description', 'tenant_invitation_issued')
            ->value('properties');
        self::assertStringContainsString('tenant.invitation.issued', $auditPayload);
        self::assertStringNotContainsString($invitation->plainTextToken, $auditPayload);
        $pendingMembershipId = DB::table('tenant_memberships')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $invitee->id)
            ->value('id');
        self::assertIsString($pendingMembershipId);
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $pendingMembershipId,
            'status' => 'pending',
            'accepted_at' => null,
        ]);
        self::assertFalse($invitee->fresh()->canAccessTenant($tenant));

        $membershipId = app(TenantInvitationService::class)->accept(
            plainTextToken: $invitation->plainTextToken,
            authenticatedUserId: (string) $invitee->id,
        );

        self::assertSame($pendingMembershipId, $membershipId);
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $membershipId,
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'role' => 'operator',
            'status' => 'active',
        ]);
        self::assertNotNull(
            DB::table('tenant_invitations')->where('id', $invitation->invitationId)->value('accepted_at'),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no longer active');

        app(TenantInvitationService::class)->accept(
            plainTextToken: $invitation->plainTextToken,
            authenticatedUserId: (string) $invitee->id,
        );
    }

    public function test_invitation_rejects_unauthorized_issuers_unknown_permissions_and_wrong_identity(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'member@example.test']);
        $wrongIdentity = User::factory()->create(['email' => 'other@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->create();
        $service = app(TenantInvitationService::class);

        try {
            $service->issue(
                tenantId: (string) $tenant->id,
                inviterUserId: (string) $outsider->id,
                email: $invitee->email,
                role: 'operator',
            );
            self::fail('Unauthorized inviter unexpectedly issued an invitation.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('not authorized', $exception->getMessage());
        }

        try {
            $service->issue(
                tenantId: (string) $tenant->id,
                inviterUserId: (string) $owner->id,
                email: $invitee->email,
                role: 'operator',
                grantedPermissions: ['catalog.manage'],
            );
            self::fail('Unknown permission unexpectedly passed validation.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Unknown tenant permission', $exception->getMessage());
        }

        $invitation = $service->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: $invitee->email,
            role: 'operator',
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('does not match');

        $service->accept($invitation->plainTextToken, (string) $wrongIdentity->id);
    }

    public function test_invitation_can_be_revoked_and_reissued_without_reusing_the_old_token(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'member@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->create();
        $service = app(TenantInvitationService::class);
        $first = $service->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: $invitee->email,
            role: 'viewer',
        );

        try {
            $service->issue(
                tenantId: (string) $tenant->id,
                inviterUserId: (string) $owner->id,
                email: $invitee->email,
                role: 'viewer',
            );
            self::fail('Duplicate active invitation unexpectedly succeeded.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('already exists', $exception->getMessage());
        }

        $second = $service->reissue($first->invitationId, (string) $owner->id);

        self::assertNotSame($first->invitationId, $second->invitationId);
        self::assertNotSame($first->plainTextToken, $second->plainTextToken);
        self::assertNotNull(
            DB::table('tenant_invitations')->where('id', $first->invitationId)->value('revoked_at'),
        );

        try {
            $service->accept($first->plainTextToken, (string) $invitee->id);
            self::fail('Revoked invitation unexpectedly succeeded.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('no longer active', $exception->getMessage());
        }

        $service->revoke($second->invitationId, (string) $owner->id);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'status' => 'revoked',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no longer active');
        $service->accept($second->plainTextToken, (string) $invitee->id);
    }

    public function test_expired_invitation_fails_closed(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'member@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->create();
        $service = app(TenantInvitationService::class);
        $invitation = $service->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: $invitee->email,
            role: 'operator',
        );
        DB::table('tenant_invitations')->where('id', $invitation->invitationId)->update([
            'expires_at' => now()->subSecond(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no longer active');
        $service->accept($invitation->plainTextToken, (string) $invitee->id);
    }

    public function test_admin_cannot_delegate_a_peer_role_or_permissions_the_admin_does_not_hold(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'preset_key' => null,
            'granted_permissions' => null,
            'status' => 'active',
            'invited_at' => now()->subDay(),
            'accepted_at' => now()->subDay(),
            'suspended_at' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);
        $service = app(TenantInvitationService::class);
        self::assertTrue($service->canManageTeam((string) $tenant->id, (string) $owner->id));
        self::assertTrue($service->canManageTeam((string) $tenant->id, (string) $admin->id));
        self::assertFalse($service->canManageTeam((string) $tenant->id, (string) User::factory()->create()->id));

        try {
            $service->issue(
                tenantId: (string) $tenant->id,
                inviterUserId: (string) $admin->id,
                email: 'peer@example.test',
                role: 'admin',
            );
            self::fail('Admin unexpectedly delegated a peer admin role.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('less privileged role', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot delegate permission');
        $service->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $admin->id,
            email: 'manager@example.test',
            role: 'manager',
            grantedPermissions: ['billing.manage'],
        );
    }
}
