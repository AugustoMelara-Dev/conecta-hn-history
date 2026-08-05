<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\Access\Enums\Role;
use App\Http\Controllers\Tenant\LegacyWorkspaceActionController;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CanonicalPanelUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::unsetEventDispatcher();
    }

    public function test_resolves_canonical_panel_url_from_primary_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantForUser($user, 'pulperia-centro');
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $url = app(CanonicalPanelUrlResolver::class)->forUser($user->refresh());

        $this->assertSame('/app/pulperia-centro', $url);
    }

    public function test_resolves_canonical_panel_url_from_accepted_membership(): void
    {
        if (! Schema::hasTable('tenant_memberships')) {
            $this->markTestSkipped('tenant_memberships table is not available in this local schema.');
        }

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = $this->createTenantForUser($owner, 'cafeteria-norte');

        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'role' => Role::MANAGER->value,
            'preset_key' => null,
            'granted_permissions' => null,
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = app(CanonicalPanelUrlResolver::class)->forUser($member);

        $this->assertSame('/app/cafeteria-norte', $url);
    }

    public function test_resolves_registration_url_when_user_has_no_tenant(): void
    {
        $user = User::factory()->create();

        $url = app(CanonicalPanelUrlResolver::class)->forUser($user);

        $this->assertSame('/registrar-negocio', $url);
    }

    public function test_resolves_superadmin_url_to_platform_panel(): void
    {
        config([
            'auth.super_admins' => ['ops@example.test'],
            'security.admin_mfa_required' => false,
        ]);

        $user = User::factory()->superAdmin()->create([
            'email' => 'ops@example.test',
        ]);

        $url = app(CanonicalPanelUrlResolver::class)->forUser($user);

        $this->assertSame('/admin', $url);
    }

    public function test_superadmin_role_without_allowlist_does_not_resolve_to_platform_panel(): void
    {
        config(['auth.super_admins' => ['ops@example.test']]);

        $user = User::factory()->superAdmin()->create([
            'email' => 'rogue@example.test',
        ]);

        $url = app(CanonicalPanelUrlResolver::class)->forUser($user);

        $this->assertSame('/registrar-negocio', $url);
    }

    public function test_workspace_get_routes_redirect_to_canonical_filament_panel(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantForUser($user, 'tienda-del-sur');
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $this->actingAs($user)
            ->get('/workspace/dashboard')
            ->assertRedirect('/app/tienda-del-sur');

        $this->actingAs($user)
            ->get('/workspace/products')
            ->assertRedirect('/app/tienda-del-sur/products');

        $this->actingAs($user)
            ->get('/workspace/products/create')
            ->assertRedirect('/app/tienda-del-sur/products/create');

        $this->actingAs($user)
            ->get('/workspace/products/product-123/edit')
            ->assertRedirect('/app/tienda-del-sur/products/product-123/edit');

        $this->actingAs($user)
            ->get('/workspace/orders')
            ->assertRedirect('/app/tienda-del-sur/orders');

        $this->actingAs($user)
            ->get('/workspace/billing')
            ->assertRedirect('/app/tienda-del-sur/manage-billing');

        $this->actingAs($user)
            ->get('/workspace/settings')
            ->assertRedirect('/app/tienda-del-sur/settings');

        $this->actingAs($user)
            ->get('/workspace/marketing')
            ->assertRedirect('/app/tienda-del-sur/manage-marketing');

        $this->actingAs($user)
            ->get('/workspace/upgrade')
            ->assertRedirect('/app/tienda-del-sur/upgrade-to-pro');
    }

    public function test_legacy_write_and_download_routes_are_deprecated_not_operational_panels(): void
    {
        $deprecatedRouteNames = [
            'tenant.products.store',
            'tenant.products.update',
            'tenant.products.destroy',
            'tenant.products.toggle-visibility',
            'tenant.orders.update-status',
            'tenant.billing.report-payment',
            'tenant.billing.upgrade',
            'tenant.settings.update-profile',
            'tenant.settings.update-schedule',
            'tenant.coupons.store',
            'tenant.coupons.toggle',
            'tenant.landing-page.update',
            'orders.print-ticket',
            'receipt.download',
            'reports.sales-pdf',
            'reports.inventory-pdf',
        ];

        foreach ($deprecatedRouteNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Expected route [{$routeName}] to remain registered as a compatibility endpoint.");
            $this->assertSame(
                LegacyWorkspaceActionController::class,
                $route->getAction('controller'),
                "Expected route [{$routeName}] to be retired behind LegacyWorkspaceActionController."
            );
        }
    }

    private function createTenantForUser(User $user, string $slug): Tenant
    {
        $attributes = [
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => Str::headline(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 0,
            'ai_reserved_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach (array_keys($attributes) as $column) {
            if (! Schema::hasColumn('tenants', $column)) {
                unset($attributes[$column]);
            }
        }

        $tenant = new Tenant;
        $tenant->forceFill($attributes);
        $tenant->save();

        return $tenant;
    }
}
