<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Infrastructure\Access\Guards\StandardTenantPermissionGuard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CanonicalTenantPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_roles_are_deny_by_default_and_inactive_memberships_never_authorize(): void
    {
        $this->app->instance(CapabilityManagerInterface::class, new class implements CapabilityManagerInterface
        {
            public function has(object $tenant, Capability $capability): bool
            {
                return true;
            }

            public function hasForTenantId(string $tenantId, Capability $capability): bool
            {
                return true;
            }

            public function allForTenant(object $tenant): array
            {
                return Capability::cases();
            }
        });

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $guard = app(StandardTenantPermissionGuard::class);

        $admin = $this->member($tenant, 'admin');
        $manager = $this->member($tenant, 'manager');
        $operator = $this->member($tenant, 'operator');
        $viewer = $this->member($tenant, 'viewer');
        $suspended = $this->member($tenant, 'admin', 'suspended');
        $revoked = $this->member($tenant, 'admin', 'revoked');

        self::assertTrue($guard->allows($owner, $tenant, Capability::STAFF_BASIC, Permission::BILLING_MANAGE));
        self::assertTrue($guard->allows($admin, $tenant, Capability::STAFF_BASIC, Permission::SETTINGS_UPDATE));
        self::assertFalse($guard->allows($admin, $tenant, Capability::STAFF_BASIC, Permission::BILLING_MANAGE));
        self::assertTrue($guard->allows($manager, $tenant, Capability::CHECKOUT_BASIC, Permission::ORDERS_MANAGE));
        self::assertFalse($guard->allows($manager, $tenant, Capability::STAFF_BASIC, Permission::SETTINGS_UPDATE));
        self::assertTrue($guard->allows($operator, $tenant, Capability::CHECKOUT_BASIC, Permission::ORDERS_FULFILL));
        self::assertFalse($guard->allows($operator, $tenant, Capability::CATALOG_BASIC, Permission::CATALOG_DELETE));
        self::assertTrue($guard->allows($viewer, $tenant, Capability::CHECKOUT_BASIC, Permission::ORDERS_VIEW));
        self::assertFalse($guard->allows($viewer, $tenant, Capability::CHECKOUT_BASIC, Permission::ORDERS_MANAGE));
        self::assertFalse($guard->allows($suspended, $tenant, Capability::STAFF_BASIC, Permission::SETTINGS_UPDATE));
        self::assertFalse($guard->allows($revoked, $tenant, Capability::STAFF_BASIC, Permission::SETTINGS_UPDATE));
    }

    private function member(Tenant $tenant, string $role, string $status = 'active'): User
    {
        $user = User::factory()->create();

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
            'revoked_at' => $status === 'revoked' ? now() : null,
            'revoked_by_user_id' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
