<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Identity\TenantMembershipService;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantMembershipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_suspend_reactivate_and_revoke_without_deleting_membership(): void
    {
        config(['activitylog.enabled' => true]);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $membershipId = $this->membership($tenant, $member, 'operator');
        $member->createToken('tenant-device', ['tenant:'.$tenant->id], now()->addDay());
        DB::table('authenticated_sessions')->insert([
            'id' => (string) Str::uuid(),
            'session_id_hash' => hash('sha256', 'member-session'),
            'user_id' => $member->id,
            'current_tenant_id' => $tenant->id,
            'device_name' => 'Browser',
            'ip_address_hash' => hash('sha256', '127.0.0.1'),
            'user_agent' => 'PHPUnit',
            'last_authenticated_at' => now(),
            'mfa_verified_at' => null,
            'last_seen_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $service = app(TenantMembershipService::class);

        $service->update(
            membershipId: $membershipId,
            actorUserId: (string) $owner->id,
            role: 'manager',
            presetKey: 'operations',
            grantedPermissions: ['analytics.export'],
        );
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $membershipId,
            'role' => 'manager',
            'preset_key' => 'operations',
            'status' => 'active',
        ]);
        self::assertNotNull(
            DB::table('authenticated_sessions')->where('user_id', $member->id)->value('revoked_at'),
        );
        self::assertSame(0, $member->tokens()->count());

        $service->suspend($membershipId, (string) $owner->id);
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $membershipId,
            'status' => 'suspended',
        ]);
        self::assertFalse($member->fresh()->canAccessTenant($tenant));

        $service->reactivate($membershipId, (string) $owner->id);
        self::assertTrue($member->fresh()->canAccessTenant($tenant));

        $service->revoke($membershipId, (string) $owner->id);
        $this->assertDatabaseHas('tenant_memberships', [
            'id' => $membershipId,
            'status' => 'revoked',
            'revoked_by_user_id' => $owner->id,
        ]);
        self::assertTrue(DB::table('tenant_memberships')->where('id', $membershipId)->exists());
        self::assertSame(4, DB::table('activity_log')
            ->whereIn('description', [
                'tenant_membership_updated',
                'tenant_membership_suspended',
                'tenant_membership_reactivated',
                'tenant_membership_revoked',
            ])
            ->count());
    }

    public function test_admin_cannot_manage_a_peer_owner_or_delegate_forbidden_permission(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $peer = User::factory()->create();
        $operator = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $adminMembership = $this->membership($tenant, $admin, 'admin');
        $peerMembership = $this->membership($tenant, $peer, 'admin');
        $operatorMembership = $this->membership($tenant, $operator, 'operator');
        $service = app(TenantMembershipService::class);

        try {
            $service->suspend($peerMembership, (string) $admin->id);
            self::fail('Admin unexpectedly suspended a peer admin.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('less privileged', $exception->getMessage());
        }

        try {
            $service->update(
                membershipId: $operatorMembership,
                actorUserId: (string) $admin->id,
                role: 'manager',
                presetKey: null,
                grantedPermissions: ['billing.manage'],
            );
            self::fail('Admin unexpectedly delegated billing management.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('cannot delegate permission', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('owner membership');
        DB::table('tenant_memberships')->where('id', $adminMembership)->update(['role' => 'owner']);
        $service->revoke($adminMembership, (string) $owner->id);
    }

    private function membership(Tenant $tenant, User $user, string $role): string
    {
        $id = (string) Str::uuid();
        DB::table('tenant_memberships')->insert([
            'id' => $id,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
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

        return $id;
    }
}
