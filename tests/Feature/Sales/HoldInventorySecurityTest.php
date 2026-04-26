<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HoldInventorySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_hold_inventory_rejects_products_from_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();

        Location::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Principal',
            'slug' => 'principal',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $foreignProduct = Product::factory()->forTenant($foreignTenant)->create();

        $response = $this->postJson('/api/v1/checkout/hold-inventory', [
            'tenant' => (string) $tenant->id,
            'session_id' => 'checkout-session-1',
            'items' => [
                [
                    'product_id' => $foreignProduct->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cart contains products that do not belong to this tenant.');
    }
}
