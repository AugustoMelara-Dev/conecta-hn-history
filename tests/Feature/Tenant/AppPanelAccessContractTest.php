<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\Access\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AppPanelAccessContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_panel_requires_tenant_ownership_or_accepted_membership(): void
    {
        $owner = User::factory()->create();
        $ownedTenant = Tenant::factory()->forUser($owner)->create(['slug' => 'owned-shop']);
        $owner->forceFill(['tenant_id' => $ownedTenant->id])->save();

        $otherOwner = User::factory()->create();
        $otherTenant = Tenant::factory()->forUser($otherOwner)->create(['slug' => 'other-shop']);

        $member = User::factory()->create();
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $ownedTenant->id,
            'user_id' => $member->id,
            'role' => Role::MANAGER->value,
            'preset_key' => null,
            'granted_permissions' => null,
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $outsider = User::factory()->create();

        $this->assertTrue($owner->canAccessTenant($ownedTenant));
        $this->assertTrue($member->canAccessTenant($ownedTenant));
        $this->assertFalse($outsider->canAccessTenant($ownedTenant));
        $this->assertFalse($owner->canAccessTenant($otherTenant));
    }

    public function test_app_panel_http_requires_authentication_and_tenant_access(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create(['slug' => 'owned-shop']);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $acceptedMember = User::factory()->create();
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $acceptedMember->id,
            'role' => Role::MANAGER->value,
            'preset_key' => null,
            'granted_permissions' => null,
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pendingMember = User::factory()->create();
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $pendingMember->id,
            'role' => Role::MANAGER->value,
            'preset_key' => null,
            'granted_permissions' => null,
            'accepted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $outsider = User::factory()->create();

        $this->get('/app/owned-shop')
            ->assertRedirect('/app/login');

        $this->actingAs($owner)
            ->get('/app/owned-shop')
            ->assertOk();

        $this->actingAs($acceptedMember)
            ->get('/app/owned-shop')
            ->assertOk();

        $this->actingAs($pendingMember)
            ->get('/app/owned-shop')
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get('/app/owned-shop')
            ->assertForbidden();
    }

    public function test_admin_panel_http_is_allowlisted_superadmin_only(): void
    {
        config([
            'auth.super_admins' => ['ops@example.test'],
            'security.admin_mfa_required' => false,
        ]);

        $normal = User::factory()->create();
        $unallowlistedSuperAdmin = User::factory()->superAdmin()->create([
            'email' => 'rogue@example.test',
        ]);
        $allowlistedSuperAdmin = User::factory()->superAdmin()->create([
            'email' => 'ops@example.test',
        ]);

        $this->get('/admin')
            ->assertRedirect('/admin/login');

        $this->actingAs($normal)
            ->get('/admin')
            ->assertForbidden();

        $this->actingAs($unallowlistedSuperAdmin)
            ->get('/admin')
            ->assertForbidden();

        $this->actingAs($allowlistedSuperAdmin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_routes_are_not_tenant_scoped(): void
    {
        $adminRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin'))
            ->values();

        $this->assertNotEmpty($adminRoutes);

        $adminRoutes->each(function ($route): void {
            $this->assertFalse(
                str_contains($route->uri(), '{tenant'),
                "Admin route [{$route->uri()}] must not be tenant-scoped."
            );
        });
    }
}
