<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\City;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\InventoryManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class InventoryMovementLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithMarket(): Tenant
    {
        return Tenant::factory()->create([
            'city_id' => City::factory()->create()->id,
        ]);
    }

    public function test_public_checkout_appends_a_managed_stock_movement_with_balances(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();

        $this->withHeader('Idempotency-Key', 'inventory-ledger-checkout-001')
            ->postJson('/api/v1/orders', $this->checkoutPayload((string) $tenant->id, (int) $product->id, 3))
            ->assertCreated();

        $this->assertDatabaseHas('inventory_movements', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'delta' => -3,
            'previous_quantity' => 10,
            'resulting_quantity' => 7,
            'reason' => 'order_consumed',
        ]);
    }

    public function test_replayed_checkout_does_not_change_stock_or_append_another_movement(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();
        $payload = $this->checkoutPayload((string) $tenant->id, (int) $product->id, 3);

        $this->withHeader('Idempotency-Key', 'inventory-ledger-replay-001')
            ->postJson('/api/v1/orders', $payload)
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'inventory-ledger-replay-001')
            ->postJson('/api/v1/orders', $payload)
            ->assertOk()
            ->assertJsonPath('data.replayed', true);

        $this->assertSame(7, (int) DB::table('products')->where('id', $product->id)->value('stock_quantity'));
        $this->assertSame(1, DB::table('inventory_movements')->where('product_id', $product->id)->count());
    }

    public function test_repeated_restock_of_an_order_is_a_no_op_after_its_first_movement(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();

        $created = $this->withHeader('Idempotency-Key', 'inventory-ledger-restock-001')
            ->postJson('/api/v1/orders', $this->checkoutPayload((string) $tenant->id, (int) $product->id, 3))
            ->assertCreated();

        $order = Order::withoutGlobalScopes()->findOrFail((int) $created->json('data.order_id'));
        $manager = app(InventoryManager::class);
        $manager->restock($order);
        $manager->restock($order->fresh(['items']));

        $this->assertSame(10, (int) DB::table('products')->where('id', $product->id)->value('stock_quantity'));
        $this->assertSame(2, DB::table('inventory_movements')->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'delta' => 3,
            'previous_quantity' => 7,
            'resulting_quantity' => 10,
            'reason' => 'order_restocked',
        ]);
    }

    public function test_reconciliation_command_reads_a_consistent_ledger_without_writing_stock(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();

        $this->withHeader('Idempotency-Key', 'inventory-ledger-reconcile-001')
            ->postJson('/api/v1/orders', $this->checkoutPayload((string) $tenant->id, (int) $product->id, 3))
            ->assertCreated();

        $this->artisan('inventory:reconcile-movements', ['--tenant' => (string) $tenant->id])
            ->assertExitCode(0);

        $this->assertSame(7, (int) DB::table('products')->where('id', $product->id)->value('stock_quantity'));
    }

    public function test_checkout_ignores_a_hostile_client_tenant_and_derives_ownership_from_products(): void
    {
        $productTenant = $this->tenantWithMarket();
        $hostileTenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($productTenant)->withStock(10)->create();

        $this->withHeader('Idempotency-Key', 'inventory-ledger-hostile-tenant-001')
            ->postJson('/api/v1/orders', $this->checkoutPayload((string) $hostileTenant->id, (int) $product->id, 3))
            ->assertCreated();

        $this->assertDatabaseHas('orders', ['tenant_id' => $productTenant->id]);
        $this->assertSame(7, (int) DB::table('products')->where('id', $product->id)->value('stock_quantity'));
        $this->assertSame(0, DB::table('orders')->where('tenant_id', $hostileTenant->id)->count());
    }

    public function test_ledger_model_rejects_zero_delta_and_inconsistent_balances(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();

        $this->expectException(\LogicException::class);

        InventoryMovement::record($this->movementAttributes($tenant, $product, [
            'delta' => 0,
            'previous_quantity' => 10,
            'resulting_quantity' => 10,
        ]));
    }

    public function test_ledger_model_rejects_balances_that_do_not_match_the_delta(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Inventory movement balances must match the signed delta.');

        InventoryMovement::record($this->movementAttributes($tenant, $product, [
            'delta' => -1,
            'previous_quantity' => 10,
            'resulting_quantity' => 10,
        ]));
    }

    public function test_reconciler_reports_structurally_invalid_rows_inserted_outside_the_model(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();

        DB::table('inventory_movements')->insert($this->movementAttributes($tenant, $product, [
            'delta' => 0,
            'previous_quantity' => 10,
            'resulting_quantity' => 10,
        ]));

        $this->artisan('inventory:reconcile-movements', ['--tenant' => (string) $tenant->id])
            ->assertExitCode(1);
    }

    public function test_reconciler_uses_persisted_sequence_when_movements_share_a_timestamp(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(8)->create();
        $timestamp = now()->startOfSecond();

        DB::table('inventory_movements')->insert([
            $this->movementAttributes($tenant, $product, [
                'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
                'delta' => -1,
                'previous_quantity' => 10,
                'resulting_quantity' => 9,
                'idempotency_key' => 'sequence-first',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]),
            $this->movementAttributes($tenant, $product, [
                'id' => '00000000-0000-4000-8000-000000000001',
                'delta' => -1,
                'previous_quantity' => 9,
                'resulting_quantity' => 8,
                'idempotency_key' => 'sequence-second',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]),
        ]);

        $this->artisan('inventory:reconcile-movements', ['--tenant' => (string) $tenant->id])
            ->assertExitCode(0);
    }

    public function test_incompatible_inventory_idempotency_payload_is_rejected_without_a_second_mutation(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();
        $manager = app(InventoryManager::class);

        $manager->validateAndLock([['product_id' => $product->id, 'quantity' => 1]], (string) $tenant->id, 'incompatible-key');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Inventory idempotency key was reused with an incompatible payload.');

        $manager->validateAndLock([['product_id' => $product->id, 'quantity' => 2]], (string) $tenant->id, 'incompatible-key');
    }

    public function test_repeated_consume_of_an_order_is_a_no_op_after_its_first_movement(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();
        $created = $this->withHeader('Idempotency-Key', 'inventory-ledger-consume-001')
            ->postJson('/api/v1/orders', $this->checkoutPayload((string) $tenant->id, (int) $product->id, 3))
            ->assertCreated();
        $order = Order::withoutGlobalScopes()->findOrFail((int) $created->json('data.order_id'));
        $manager = app(InventoryManager::class);

        $manager->restock($order);
        $manager->consume($order->fresh(['items']));
        $manager->consume($order->fresh(['items']));

        $this->assertSame(7, (int) DB::table('products')->where('id', $product->id)->value('stock_quantity'));
        $this->assertSame(3, DB::table('inventory_movements')->where('product_id', $product->id)->count());
    }

    public function test_inventory_history_blocks_hard_deletion_of_a_referenced_product(): void
    {
        $tenant = $this->tenantWithMarket();
        $product = Product::factory()->forTenant($tenant)->withStock(10)->create();

        $this->withHeader('Idempotency-Key', 'inventory-ledger-retention-001')
            ->postJson('/api/v1/orders', $this->checkoutPayload((string) $tenant->id, (int) $product->id, 3))
            ->assertCreated();

        $this->expectException(QueryException::class);

        DB::table('products')->where('id', $product->id)->delete();
    }

    /** @param array<string, mixed> $overrides */
    private function movementAttributes(Tenant $tenant, Product $product, array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'delta' => -1,
            'previous_quantity' => 10,
            'resulting_quantity' => 9,
            'reason' => 'test_adjustment',
            'actor_type' => null,
            'actor_id' => null,
            'reference_type' => 'test',
            'reference_id' => 'test-reference',
            'idempotency_key' => 'test:'.Str::uuid(),
            'payload_hash' => hash('sha256', Str::random()),
            'metadata' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(string $tenantId, int $productId, int $quantity): array
    {
        return [
            'tenant_id' => $tenantId,
            'customer_name' => 'Cliente ledger',
            'customer_phone' => '9999-1111',
            'customer_email' => 'ledger@example.test',
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $productId,
                'quantity' => $quantity,
                'options' => [],
            ]],
        ];
    }
}
