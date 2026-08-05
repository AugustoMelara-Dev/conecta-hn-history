<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardBridgeContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_business_owner_to_filament_app_panel(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'cafeteria-norte',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertRedirect('/app/cafeteria-norte');
    }

    public function test_dashboard_redirects_user_without_tenant_to_business_registration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/registrar-negocio');
    }

    public function test_dashboard_redirects_superadmin_to_superadmin_panel(): void
    {
        config([
            'auth.super_admins' => ['ops@example.test'],
            'security.admin_mfa_required' => false,
        ]);

        $superAdmin = User::factory()->superAdmin()->create([
            'email' => 'ops@example.test',
        ]);

        $this->actingAs($superAdmin)
            ->get('/dashboard')
            ->assertRedirect('/admin');
    }

    public function test_dashboard_does_not_treat_unallowlisted_superadmin_role_as_platform_admin(): void
    {
        config(['auth.super_admins' => ['ops@example.test']]);

        $user = User::factory()->superAdmin()->create([
            'email' => 'rogue@example.test',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/registrar-negocio');
    }

    public function test_legacy_workspace_get_actions_redirect_to_canonical_panel(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'cafeteria-norte',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $this->actingAs($owner)
            ->get('/workspace/orders/order-123/print-ticket')
            ->assertRedirect('/app/cafeteria-norte/orders');
    }

    public function test_legacy_workspace_mutations_remain_gone(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'cafeteria-norte',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $this->actingAs($owner)
            ->post('/workspace/products', [
                'name' => 'Cafe',
            ])
            ->assertStatus(410);
    }

    public function test_legacy_workspace_mutation_routes_are_all_gone_at_runtime(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'cafeteria-norte',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $mutations = [
            ['post', '/workspace/products'],
            ['put', '/workspace/products/product-123'],
            ['delete', '/workspace/products/product-123'],
            ['put', '/workspace/products/product-123/toggle-visibility'],
            ['put', '/workspace/orders/order-123/status'],
            ['post', '/workspace/billing/report-payment'],
            ['post', '/workspace/billing/upgrade'],
            ['post', '/workspace/settings/profile'],
            ['put', '/workspace/settings/schedule'],
            ['post', '/workspace/marketing/coupons'],
            ['put', '/workspace/marketing/coupons/coupon-123/toggle'],
            ['put', '/workspace/landing-page'],
        ];

        foreach ($mutations as [$method, $uri]) {
            $this->actingAs($owner)
                ->{$method}($uri, ['name' => 'legacy write'])
                ->assertStatus(410);
        }
    }

    public function test_legacy_workspace_json_mutations_return_gone_contract(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'cafeteria-norte',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $this->actingAs($owner)
            ->postJson('/workspace/products', ['name' => 'Cafe'])
            ->assertStatus(410)
            ->assertJsonPath('message', 'Esta accion del workspace legacy fue retirada. Usa el panel operativo canonico en /app/{tenant}.');
    }
}
