<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Application\Sales\Listeners\RestoreStockOnCancellation;
use App\Events\Order\OrderCancelled;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RestoreStockOnCancellationTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_restock_requires_the_event_tenant_to_own_the_order_and_replays_safely(): void
    {
        $owner = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $product = Product::factory()->forTenant($owner)->create([
            'manage_stock' => true,
            'stock_quantity' => 5,
        ]);
        $order = Order::factory()->forTenant($owner)->create();
        $order->items()->create([
            'product_id' => $product->getKey(),
            'product_name' => $product->name,
            'product_price' => '10.00',
            'unit_price' => '10.00',
            'quantity' => 2,
            'total_price' => '20.00',
            'line_total' => '20.00',
        ]);
        $listener = app(RestoreStockOnCancellation::class);

        $hostile = OrderCancelled::create((string) $other->getKey(), $order->getKey(), (string) $order->order_number, 'mismatched tenant');
        $listener->handle($hostile);

        self::assertSame(5, (int) $product->fresh()->stock_quantity);
        self::assertSame(0, DB::table('inventory_movements')->where('product_id', $product->getKey())->count());

        $valid = OrderCancelled::create((string) $owner->getKey(), $order->getKey(), (string) $order->order_number, 'valid cancellation');
        $listener->handle($valid);
        $listener->handle($valid);

        self::assertSame(7, (int) $product->fresh()->stock_quantity);
        self::assertSame(1, DB::table('inventory_movements')->where('product_id', $product->getKey())->count());
    }
}
