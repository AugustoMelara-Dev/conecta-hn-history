<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Interaction;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InteractionEndpointTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_interaction_endpoint_accepts_uuid_and_persists_owned_product(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant)->create();

        $this->postJson('/api/v1/interactions', [
            'tenant_id' => (string) $tenant->getKey(),
            'type' => 'view',
            'product_id' => $product->getKey(),
        ])->assertCreated();

        $this->assertDatabaseHas('interactions', [
            'tenant_id' => (string) $tenant->getKey(),
            'product_id' => $product->getKey(),
            'type' => 'view',
        ]);
    }

    public function test_legacy_interaction_endpoint_rejects_cross_tenant_product(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($otherTenant)->create();

        $this->postJson('/api/v1/interactions', [
            'tenant_id' => (string) $tenant->getKey(),
            'type' => 'view',
            'product_id' => $product->getKey(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        self::assertSame(0, Interaction::query()->withoutGlobalScopes()->count());
    }
}
