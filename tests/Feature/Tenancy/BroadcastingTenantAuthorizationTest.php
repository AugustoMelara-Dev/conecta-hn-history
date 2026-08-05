<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Infrastructure\Broadcasting\TenantChannelAuthorizer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BroadcastingTenantAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_realtime_channels_use_active_canonical_membership_and_admin_role_boundaries(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $operator = User::factory()->create();
        $suspendedAdmin = User::factory()->create();
        $outsider = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $otherTenant = Tenant::factory()->create();
        $this->membership($tenant, $admin, 'admin', 'active');
        $this->membership($tenant, $operator, 'operator', 'active');
        $this->membership($tenant, $suspendedAdmin, 'admin', 'suspended');
        $authorizer = app(TenantChannelAuthorizer::class);

        self::assertTrue($authorizer->canAccessTenant($owner, (string) $tenant->id));
        self::assertTrue($authorizer->canAccessTenant($admin, (string) $tenant->id));
        self::assertTrue($authorizer->canAccessTenant($operator, (string) $tenant->id));
        self::assertFalse($authorizer->canAccessTenant($suspendedAdmin, (string) $tenant->id));
        self::assertFalse($authorizer->canAccessTenant($outsider, (string) $tenant->id));
        self::assertFalse($authorizer->canAccessTenant($admin, (string) $otherTenant->id));

        self::assertTrue($authorizer->canAccessTenantAdmin($owner, (string) $tenant->id));
        self::assertTrue($authorizer->canAccessTenantAdmin($admin, (string) $tenant->id));
        self::assertFalse($authorizer->canAccessTenantAdmin($operator, (string) $tenant->id));
        self::assertFalse($authorizer->canAccessTenantAdmin($suspendedAdmin, (string) $tenant->id));
    }

    public function test_channel_routes_do_not_trust_legacy_user_tenant_id_or_platform_role(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 3).'/routes/channels.php');

        self::assertStringContainsString('TenantChannelAuthorizer', $routes);
        self::assertStringNotContainsString('$user->tenant_id', $routes);
        self::assertStringNotContainsString("'super_admin'", $routes);
    }

    private function membership(
        Tenant $tenant,
        User $user,
        string $role,
        string $status,
    ): void {
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'preset_key' => null,
            'granted_permissions' => null,
            'status' => $status,
            'invited_at' => now()->subDay(),
            'accepted_at' => now()->subDay(),
            'suspended_at' => $status === 'suspended' ? now() : null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);
    }
}
