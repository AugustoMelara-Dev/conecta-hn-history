<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Broadcasting\ChannelRegistry;
use App\Domain\TenantGovernance\Events\TenantProfileEnhanced;
use App\Events\Broadcast\OrderPaidBroadcast;
use App\Events\Inventory\LowStockThresholdReached;
use App\Events\Order\OrderPaid;
use App\Listeners\LowStockListener;
use DateTimeImmutable;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DomainEventUuidContractTest extends TestCase
{
    #[Test]
    public function order_event_preserves_tenant_and_aggregate_uuids_across_serialization(): void
    {
        $tenantId = '10000000-0000-4000-8000-000000000001';
        $orderId = '20000000-0000-4000-8000-000000000002';

        $event = OrderPaid::create(
            tenantId: $tenantId,
            orderId: $orderId,
            orderNumber: 'ORD-UUID-1',
            transactionId: 'txn-uuid-1',
            paymentMethod: 'card',
            amountCents: 49900,
        );

        $restored = OrderPaid::fromPrimitives($event->toPrimitives());

        self::assertSame($tenantId, $restored->tenantId()->toString());
        self::assertSame($orderId, $restored->aggregateId());
        self::assertSame($orderId, $restored->toPrimitives()['order_id']);
    }

    #[Test]
    public function uuid_ids_route_to_their_exact_private_broadcast_channel(): void
    {
        $tenantId = '30000000-0000-4000-8000-000000000003';
        $orderId = '40000000-0000-4000-8000-000000000004';

        $event = OrderPaidBroadcast::fromDomainData(
            tenantId: $tenantId,
            orderId: $orderId,
            orderNumber: 'ORD-UUID-2',
            amountCents: 49900,
            currency: 'HNL',
            paymentMethod: 'card',
            paidAt: new DateTimeImmutable,
        );

        self::assertSame('tenant.'.$tenantId, ChannelRegistry::tenantName($tenantId));
        self::assertSame($tenantId, $event->tenantId);
        self::assertSame($orderId, $event->orderId);
        self::assertSame('private-tenant.'.$tenantId, $event->broadcastOn()[0]->name);
    }

    #[Test]
    public function tenant_profile_event_does_not_truncate_the_owner_uuid(): void
    {
        $tenantId = '50000000-0000-4000-8000-000000000005';
        $userId = '60000000-0000-4000-8000-000000000006';

        $restored = TenantProfileEnhanced::fromPayload([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'occurred_at' => '2026-07-21T12:00:00+00:00',
            'enhanced_fields' => ['description'],
        ]);

        self::assertSame($tenantId, $restored->tenant_id);
        self::assertSame($userId, $restored->user_id);
        self::assertSame($userId, $restored->toPayload()['user_id']);
    }

    #[Test]
    public function low_stock_event_carries_queue_safe_snapshots_for_its_active_listener(): void
    {
        Queue::fake();

        $tenantId = '70000000-0000-4000-8000-000000000007';
        $productId = '80000000-0000-4000-8000-000000000008';
        $product = new \stdClass;
        $product->id = $productId;
        $product->tenant_id = $tenantId;
        $product->name = 'Producto UUID';
        $product->sku = 'UUID-SKU';

        $event = new LowStockThresholdReached($product, 0, 5);
        $restored = LowStockThresholdReached::fromPrimitives($event->toPrimitives());

        self::assertSame($tenantId, $restored->tenantId()->toString());
        self::assertSame($productId, $restored->aggregateId());
        self::assertSame('Producto UUID', $restored->productName);
        self::assertSame('UUID-SKU', $restored->productSku);
        self::assertSame('critical', $restored->severity());

        (new LowStockListener)->handle($restored);

        self::assertTrue(cache()->has(
            'low_stock_alert:'.$tenantId.':'.$productId.':'.now()->format('Y-m-d')
        ));
    }
}
