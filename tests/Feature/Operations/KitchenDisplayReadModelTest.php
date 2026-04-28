<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Operations\KitchenDisplayReadModel;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class KitchenDisplayReadModelTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_exposes_active_food_orders_when_kitchen_display_capability_is_enabled(): void
    {
        $tenant = Tenant::factory()->create(['business_type' => 'food']);
        $location = Location::create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cocina Centro',
            'slug' => 'cocina-centro',
            'is_active' => true,
            'is_primary' => true,
        ]);
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'Baleada especial']);
        $order = Order::factory()
            ->forTenant($tenant)
            ->confirmed()
            ->create(['location_id' => $location->getKey(), 'order_number' => 'ORD-KDS-1']);

        OrderItem::factory()->forOrder($order)->forProduct($product)->withQuantity(2)->create();

        $board = app(KitchenDisplayReadModel::class)->forTenant($tenant);

        self::assertTrue($board['enabled']);
        self::assertSame('ORD-KDS-1', $board['columns'][Order::STATUS_CONFIRMED][0]['order_number']);
        self::assertSame('Cocina Centro', $board['columns'][Order::STATUS_CONFIRMED][0]['location']);
        self::assertSame('Baleada especial', $board['columns'][Order::STATUS_CONFIRMED][0]['items'][0]['name']);
    }

    #[Test]
    public function it_stays_disabled_for_business_types_without_kitchen_display_capability(): void
    {
        $tenant = Tenant::factory()->create(['business_type' => 'service']);

        $board = app(KitchenDisplayReadModel::class)->forTenant($tenant);

        self::assertFalse($board['enabled']);
        self::assertSame([], $board['columns']);
    }
}
