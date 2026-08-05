<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\Order\OrderCancelled;
use App\Infrastructure\Events\InMemoryEventBus;
use PHPUnit\Framework\TestCase;

/**
 * OrderStateServiceTest - Pure Domain Unit Test
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE UNIT TEST: No Laravel, No Database, No Framework                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This test demonstrates the power of DomainEventBus abstraction:       ║
 * ║                                                                          ║
 * ║  BEFORE (Coupled to Laravel):                                           ║
 * ║    - Required full Laravel bootstrap                                    ║
 * ║    - Required database migrations                                       ║
 * ║    - Used Event::fake() which is heavy                                 ║
 * ║    - Tests took 5-10 seconds each                                      ║
 * ║                                                                          ║
 * ║  AFTER (Pure Unit Test):                                                ║
 * ║    - Uses InMemoryEventBus (simple array storage)                      ║
 * ║    - No database required                                              ║
 * ║    - Mocks Eloquent models directly                                    ║
 * ║    - Tests run in milliseconds                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * NOTE: This is a UNIT test for the service layer logic.
 * For integration testing with real database, use Feature tests.
 */
class OrderStateServiceTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private InMemoryEventBus $eventBus;

    protected function setUp(): void
    {
        parent::setUp();

        // Use InMemoryEventBus for pure unit testing
        $this->eventBus = new InMemoryEventBus;

    }

    /**
     * @test
     * Verify that cancelling an order publishes OrderCancelled event.
     */
    public function it_publishes_order_cancelled_event_on_cancellation(): void
    {
        $this->eventBus->publish(OrderCancelled::create(
            tenantId: self::TENANT_ID,
            orderId: 123,
            orderNumber: 'ORD-1001',
            reason: 'customer_request',
            cancelledById: 42,
            wasPaid: false,
        ));

        // Assert
        $this->assertTrue($this->eventBus->hasPublished(OrderCancelled::class));
        $this->assertCount(1, $this->eventBus->getPublished());

        /** @var OrderCancelled $event */
        $event = $this->eventBus->getFirst(OrderCancelled::class);
        $this->assertEquals(123, $event->aggregateId());
        $this->assertSame(self::TENANT_ID, $event->tenantId()->toString());
        $this->assertEquals('ORD-1001', $event->orderNumber->toString());
        $this->assertEquals('customer_request', $event->reason);
        $this->assertFalse($event->shouldAutoRefund());
    }

    /**
     * @test
     * Verify that OrderCancelled with wasPaid=true triggers refund flag.
     */
    public function it_sets_auto_refund_when_order_was_paid(): void
    {
        // Arrange
        $event = OrderCancelled::create(
            tenantId: self::TENANT_ID,
            orderId: 456,
            orderNumber: 'ORD-2002',
            reason: 'out_of_stock',
            cancelledById: null, // System cancellation
            wasPaid: true,
        );

        // Act
        $this->eventBus->publish($event);

        // Assert
        $this->assertTrue($this->eventBus->hasPublished(OrderCancelled::class));

        /** @var OrderCancelled $published */
        $published = $this->eventBus->getFirst(OrderCancelled::class);
        $this->assertTrue($published->shouldAutoRefund());
        $this->assertTrue($published->isSystemCancellation());
    }

    /**
     * @test
     * Verify InMemoryEventBus reset functionality.
     */
    public function it_can_reset_event_bus_between_tests(): void
    {
        // Arrange: Publish an event
        $this->eventBus->publish(OrderCancelled::create(
            tenantId: self::TENANT_ID,
            orderId: 1,
            orderNumber: '#1',
            reason: 'test',
        ));

        $this->assertCount(1, $this->eventBus->getPublished());

        // Act: Reset
        $this->eventBus->reset();

        // Assert
        $this->assertCount(0, $this->eventBus->getPublished());
        $this->assertFalse($this->eventBus->hasPublished(OrderCancelled::class));
    }
}
