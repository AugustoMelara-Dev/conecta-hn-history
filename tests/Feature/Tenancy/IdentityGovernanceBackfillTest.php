<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Identity\IdentityGovernanceBackfill;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class IdentityGovernanceBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_consolidates_owners_and_accepted_legacy_members_without_granting_pending_access(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $pending = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->delete();
        DB::table('tenant_users')->insert([
            [
                'tenant_id' => $tenant->id,
                'user_id' => $manager->id,
                'role' => 'manager',
                'can_manage_products' => true,
                'can_manage_orders' => true,
                'can_view_analytics' => true,
                'can_manage_settings' => false,
                'can_manage_team' => true,
                'can_manage_billing' => false,
                'invited_at' => now()->subDay(),
                'accepted_at' => now()->subHour(),
                'invitation_token' => null,
                'invited_by' => $owner->email,
                'last_access_at' => null,
                'created_at' => now()->subDay(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $pending->id,
                'role' => 'employee',
                'can_manage_products' => true,
                'can_manage_orders' => true,
                'can_view_analytics' => false,
                'can_manage_settings' => false,
                'can_manage_team' => false,
                'can_manage_billing' => false,
                'invited_at' => now()->subDay(),
                'accepted_at' => null,
                'invitation_token' => 'legacy-plaintext-token',
                'invited_by' => $owner->email,
                'last_access_at' => null,
                'created_at' => now()->subDay(),
                'updated_at' => now(),
            ],
        ]);

        $first = app(IdentityGovernanceBackfill::class)->run();
        $second = app(IdentityGovernanceBackfill::class)->run();

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $pending->id,
        ]);
        $permissions = json_decode(
            (string) DB::table('tenant_memberships')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $manager->id)
                ->value('granted_permissions'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertContains('catalog.update', $permissions);
        self::assertContains('orders.manage', $permissions);
        self::assertContains('analytics.view', $permissions);
        self::assertContains('staff.manage', $permissions);
        self::assertNotContains('billing.manage', $permissions);
        self::assertSame(2, DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->count());
        self::assertSame(1, $first->pendingInvitationsRequiringReissue);
        self::assertSame(1, $second->pendingInvitationsRequiringReissue);
        self::assertSame('legacy-plaintext-token', DB::table('tenant_users')->where('user_id', $pending->id)->value('invitation_token'));
        self::assertSame(0, DB::table('tenant_invitations')->count());
    }
}
