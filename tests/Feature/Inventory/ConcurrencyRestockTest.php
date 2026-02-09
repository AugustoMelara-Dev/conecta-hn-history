<?php

declare(strict_types=1);

/**
 * ConcurrencyRestockTest - Proof-of-Fix for TOCTOU Race Condition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CONCURRENCY TEST: Validates lockForUpdate() in InventoryManager        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  APPROACH: 100% RAW DB setup to avoid LogsActivity MissingAttribute     ║
 * ║  issues with factories. Order retrieval uses Eloquent for realism.      ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

namespace Tests\Feature\Inventory;

use App\Models\Order;
use App\Services\InventoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrencyRestockTest extends TestCase
{
    use RefreshDatabase;

    private InventoryManager $inventoryManager;
    private string $tenantId;
    private string $userId;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryManager = new InventoryManager();

        // ═══════════════════════════════════════════════════════════════════════
        // RAW DB SETUP: Avoids LogsActivity MissingAttribute issues with factories
        // ═══════════════════════════════════════════════════════════════════════

        // 1. Create User (UUID)
        $this->userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $this->userId,
            'name' => 'Test User',
            'email' => 'test-' . Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Create Tenant (UUID) - all required columns
        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'id' => $this->tenantId,
            'user_id' => $this->userId,
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . Str::random(6),
            'plan' => 'SEED',
            'is_active' => true,
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 1.00,
            'ai_reserved_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Create Product - out of stock for race condition testing
        $this->productId = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Product',
            'slug' => 'test-product-' . Str::random(6),
            'price' => 100.00,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'stock_status' => 'out_of_stock',
            'is_visible' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPER: Create order with product quantity
    // ═══════════════════════════════════════════════════════════════════════════

    private function createOrderWithQuantity(int $quantity): Order
    {
        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'order_number' => '#' . rand(10000, 99999),
            'customer_name' => 'Test Customer',
            'customer_phone' => '9999-9999',
            'subtotal' => 500.00,
            'total' => 500.00,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'source' => 'web',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'product_name' => 'Test Product',
            'product_price' => 100.00,
            'unit_price' => 100.00,
            'quantity' => $quantity,
            'total_price' => 100.00 * $quantity,
            'line_total' => 100.00 * $quantity, // Required by original schema
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::withoutGlobalScopes()->find($orderId);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: BASELINE - Single restock
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_single_restock_increments_stock(): void
    {
        $order = $this->createOrderWithQuantity(5);

        $this->inventoryManager->restock($order);

        $stock = DB::table('products')->where('id', $this->productId)->value('stock_quantity');
        $this->assertEquals(5, $stock);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: Sequential restocks accumulate
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_sequential_restocks_accumulate(): void
    {
        $order1 = $this->createOrderWithQuantity(5);
        $order2 = $this->createOrderWithQuantity(5);

        $this->inventoryManager->restock($order1);
        $this->inventoryManager->restock($order2);

        $stock = DB::table('products')->where('id', $this->productId)->value('stock_quantity');
        $this->assertEquals(10, $stock);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: CRITICAL - lockForUpdate() verification
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_restock_uses_lock_for_update(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite no soporta row-level locking');
        }

        DB::enableQueryLog();

        $order = $this->createOrderWithQuantity(5);
        $this->inventoryManager->restock($order);

        $queries = DB::getQueryLog();
        $hasLock = collect($queries)->contains(fn($q) => stripos($q['query'], 'for update') !== false);

        $this->assertTrue($hasLock, 
            "CRITICAL: restock() NO usa lockForUpdate()!\n" .
            "Queries: " . json_encode(array_column($queries, 'query'), JSON_PRETTY_PRINT)
        );

        DB::disableQueryLog();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: Tenant isolation in lock query
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_lock_includes_tenant_isolation(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite no soporta este test');
        }

        DB::enableQueryLog();

        $order = $this->createOrderWithQuantity(5);
        $this->inventoryManager->restock($order);

        $queries = DB::getQueryLog();
        $hasTenantLock = collect($queries)->contains(function ($q) {
            $sql = strtolower($q['query']);
            return strpos($sql, 'for update') !== false && strpos($sql, 'tenant_id') !== false;
        });

        $this->assertTrue($hasTenantLock, 'lockForUpdate() debe incluir tenant_id');

        DB::disableQueryLog();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: Stock status transition
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_restock_transitions_status(): void
    {
        // Verify initial status
        $initialStatus = DB::table('products')->where('id', $this->productId)->value('stock_status');
        $this->assertEquals('out_of_stock', $initialStatus);

        $order = $this->createOrderWithQuantity(10);
        $this->inventoryManager->restock($order);

        $finalStatus = DB::table('products')->where('id', $this->productId)->value('stock_status');
        $this->assertEquals('in_stock', $finalStatus);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: Unmanaged stock skipped
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_skips_unmanaged_products(): void
    {
        // Create unmanaged product
        $unmanagedId = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenantId,
            'name' => 'Unmanaged Product',
            'slug' => 'unmanaged-' . Str::random(6),
            'price' => 50.00,
            'stock_quantity' => 0,
            'manage_stock' => false, // UNMANAGED
            'stock_status' => 'in_stock',
            'is_visible' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create order with unmanaged product
        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'order_number' => '#' . rand(10000, 99999),
            'customer_name' => 'Test Customer',
            'customer_phone' => '9999-9999',
            'subtotal' => 50.00,
            'total' => 50.00,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $unmanagedId,
            'product_name' => 'Unmanaged',
            'product_price' => 50.00,
            'unit_price' => 50.00,
            'quantity' => 5,
            'total_price' => 250.00,
            'line_total' => 250.00, // Required by original schema
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->inventoryManager->restock(Order::withoutGlobalScopes()->find($orderId));

        $stock = DB::table('products')->where('id', $unmanagedId)->value('stock_quantity');
        $this->assertEquals(0, $stock, 'Productos no gestionados no deben cambiar');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 7: Multiple products in order
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_handles_multiple_products(): void
    {
        // Create second product
        $product2Id = DB::table('products')->insertGetId([
            'tenant_id' => $this->tenantId,
            'name' => 'Product 2',
            'slug' => 'product-2-' . Str::random(6),
            'price' => 200.00,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'stock_status' => 'out_of_stock',
            'is_visible' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create order with both products
        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'order_number' => '#' . rand(10000, 99999),
            'customer_name' => 'Test Customer',
            'customer_phone' => '9999-9999',
            'subtotal' => 1100.00,
            'total' => 1100.00,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            ['order_id' => $orderId, 'product_id' => $this->productId, 'product_name' => 'P1', 'product_price' => 100, 'unit_price' => 100, 'quantity' => 5, 'total_price' => 500, 'line_total' => 500, 'created_at' => now(), 'updated_at' => now()],
            ['order_id' => $orderId, 'product_id' => $product2Id, 'product_name' => 'P2', 'product_price' => 200, 'unit_price' => 200, 'quantity' => 3, 'total_price' => 600, 'line_total' => 600, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->inventoryManager->restock(Order::withoutGlobalScopes()->find($orderId));

        $stock1 = DB::table('products')->where('id', $this->productId)->value('stock_quantity');
        $stock2 = DB::table('products')->where('id', $product2Id)->value('stock_quantity');

        $this->assertEquals(5, $stock1);
        $this->assertEquals(3, $stock2);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 8: Transaction integrity
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_transaction_closes_properly(): void
    {
        $order = $this->createOrderWithQuantity(5);

        $before = DB::transactionLevel();
        $this->inventoryManager->restock($order);
        $after = DB::transactionLevel();

        $this->assertEquals($before, $after, 'Transaction debe cerrarse correctamente');
    }
}
