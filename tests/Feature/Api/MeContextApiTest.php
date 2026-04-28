<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MeContextApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_receives_business_surface_context_and_capability_map(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'negocio-contexto',
            'business_type' => 'retail',
        ]);

        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/me/context');

        $response->assertOk()
            ->assertJsonPath('data.home_surface', 'business_app')
            ->assertJsonPath('data.audience', 'owner')
            ->assertJsonPath('data.active_tenant.slug', 'negocio-contexto')
            ->assertJsonPath('data.active_tenant.business_type', 'retail')
            ->assertJsonPath('data.surface_bootstrap.context.home_surface', 'business_app');

        $map = (array) $response->json('data.feature_capability_map');
        $this->assertArrayHasKey('retail', $map);
        $this->assertContains('catalog_basic', (array) data_get($map, 'retail.capabilities', []));
    }

    public function test_customer_receives_user_surface_context_without_active_tenant(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/v1/me/context');

        $response->assertOk()
            ->assertJsonPath('data.home_surface', 'user_app')
            ->assertJsonPath('data.audience', 'customer')
            ->assertJsonPath('data.active_tenant', null)
            ->assertJsonPath('data.surface_bootstrap.context.home_surface', 'user_app');
    }
}
