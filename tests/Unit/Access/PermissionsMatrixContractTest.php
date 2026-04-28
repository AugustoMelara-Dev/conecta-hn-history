<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PermissionsMatrixContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_permissions_matrix_modules_are_enforced_for_all_roles(): void
    {
        /** @var array<string, array{capability: string, role_permissions: array<string, string>}> $modules */
        $modules = (array) config('permissions-matrix.modules', []);
        self::assertNotEmpty($modules, 'permissions-matrix.modules contract must not be empty.');

        foreach (Role::cases() as $role) {
            $user = User::factory()->create();
            $tenant = Tenant::factory()->create([
                'business_type' => BusinessType::SERVICE,
            ]);

            TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => $role->value,
                'accepted_at' => now(),
            ]);

            $guard = app(TenantPermissionGuardInterface::class);

            foreach ($modules as $moduleKey => $module) {
                $capability = Capability::from((string) $module['capability']);
                $permissionValue = $module['role_permissions'][$role->value] ?? null;

                self::assertIsString($permissionValue, "Module {$moduleKey} must define permission for role {$role->value}.");

                $permission = Permission::from((string) $permissionValue);

                self::assertTrue(
                    $guard->allows($user, $tenant, $capability, $permission),
                    "Role {$role->value} should allow {$permission->value} in module {$moduleKey}."
                );
            }
        }
    }
}
