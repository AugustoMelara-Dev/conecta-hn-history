<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class OrderApiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_lists_only_orders_from_uuid_tenant_context(): void
    {
        [$user, $tenant] = $this->tenantUser();
        [, $otherTenant] = $this->tenantUser();
        $ownedOrder = Order::factory()->forTenant($tenant)->create(['user_id' => $user->id]);
        $foreignOrder = Order::factory()->forTenant($otherTenant)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownedOrder->id));
        $this->assertFalse($ids->contains($foreignOrder->id));
    }

    public function test_unrelated_tenant_header_is_forbidden(): void
    {
        [$user] = $this->tenantUser();
        [, $otherTenant] = $this->tenantUser();
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-ID', (string) $otherTenant->id)
            ->getJson('/api/v1/orders')
            ->assertForbidden();
    }

    public function test_foreign_order_is_hidden_from_show_status_and_cancel_mutations(): void
    {
        [$user] = $this->tenantUser();
        [, $otherTenant] = $this->tenantUser();
        $foreignOrder = Order::factory()->forTenant($otherTenant)->pending()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$foreignOrder->id}")
            ->assertNotFound();

        $this->patchJson("/api/v1/orders/{$foreignOrder->id}/status", [
            'status' => Order::STATUS_CONFIRMED,
        ])->assertNotFound();

        $this->deleteJson("/api/v1/orders/{$foreignOrder->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('orders', [
            'id' => $foreignOrder->id,
            'tenant_id' => $otherTenant->id,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    public function test_public_order_route_reaches_validation_instead_of_missing_dependency(): void
    {
        $this->postJson('/api/v1/orders', [])->assertUnprocessable();
    }

    public function test_public_checkout_uses_server_prices_uuid_tenant_and_idempotent_replay(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => City::factory()->create()->id]);
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create(['price' => 125.50]);
        $payload = $this->checkoutPayload((string) $tenant->id, (int) $product->id, 2);

        $created = $this->withHeader('Idempotency-Key', 'checkout-safe-001')
            ->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 251)
            ->assertJsonPath('data.replayed', false);

        $orderId = (int) $created->json('data.order_id');

        $this->withHeader('Idempotency-Key', 'checkout-safe-001')
            ->postJson('/api/v1/orders', $payload)
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.replayed', true);

        $changedPayload = $this->checkoutPayload((string) $tenant->id, (int) $product->id, 1);
        $this->withHeader('Idempotency-Key', 'checkout-safe-001')
            ->postJson('/api/v1/orders', $changedPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'tenant_id' => $tenant->id,
            'total' => 251,
            'currency' => 'HNL',
            'idempotency_key' => 'checkout-safe-001',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 125.50,
            'quantity' => 2,
            'line_total' => 251,
        ]);
        $this->assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(8, (int) DB::table('products')->where('id', $product->id)->value('stock_quantity'));
    }

    public function test_public_checkout_rejects_a_cart_that_mixes_products_from_different_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $ownedProduct = Product::factory()->forTenant($tenant)->withStock(10)->create();
        $foreignProduct = Product::factory()->forTenant($otherTenant)->withStock(10)->create();
        $payload = $this->checkoutPayload((string) $tenant->id, (int) $ownedProduct->id, 2);
        $payload['items'][] = [
            'product_id' => $foreignProduct->id,
            'quantity' => 2,
            'options' => [],
        ];

        $this->withHeader('Idempotency-Key', 'checkout-hostile-001')
            ->postJson('/api/v1/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(10, (int) DB::table('products')->where('id', $ownedProduct->id)->value('stock_quantity'));
        $this->assertSame(10, (int) DB::table('products')->where('id', $foreignProduct->id)->value('stock_quantity'));
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(string $tenantId, int $productId, int $quantity): array
    {
        return [
            'tenant_id' => $tenantId,
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '9999-9999',
            'customer_email' => 'cliente@example.test',
            'customer_address' => null,
            'customer_notes' => null,
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $productId,
                'quantity' => $quantity,
                'options' => [],
            ]],
        ];
    }

    /**
     * @return array{User, Tenant}
     */
    private function tenantUser(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create();
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user->refresh(), $tenant];
    }
}
