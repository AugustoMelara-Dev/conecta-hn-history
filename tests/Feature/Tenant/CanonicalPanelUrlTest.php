<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\Access\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CanonicalPanelUrlTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_legacy_write_and_download_routes_keep_their_current_methods(): void
    {
        $this->assertSame(['POST'], Route::getRoutes()->getByName('tenant.products.store')->methods());
        $this->assertSame(['PUT'], Route::getRoutes()->getByName('tenant.products.update')->methods());
        $this->assertSame(['DELETE'], Route::getRoutes()->getByName('tenant.products.destroy')->methods());
        $this->assertSame(['PUT'], Route::getRoutes()->getByName('tenant.orders.update-status')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('tenant.billing.report-payment')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('tenant.billing.upgrade')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('orders.print-ticket')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('receipt.download')->methods());
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

        $tenant = new Tenant();
        $tenant->forceFill($attributes);
        $tenant->save();

        return $tenant;
    }
}
