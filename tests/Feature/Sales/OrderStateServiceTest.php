<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Domain\Sales\Events\OrderCancelled;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Tenant;
use App\Services\OrderStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderStateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        OrderEvent::reguard();

        parent::tearDown();
    }

    #[Test]
    public function it_transitions_orders_without_crashing_on_paid_checks(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'FSM Tenant',
        ]);

        $tenantContext = app(TenantContext::class);
        $tenantContext->reset();
        $tenantContext->setTenant((string) $tenant->id);
        OrderEvent::unguard();

        $order = Order::factory()
            ->forTenant($tenant)
            ->create([
                'status' => Order::STATUS_PENDING,
                'payment_status' => 'pending',
            ]);

        $dispatcher = new SalesOrderStateEventDispatcher;
        $service = new OrderStateService($dispatcher);

        $updatedOrder = $service->transition($order, Order::STATUS_CANCELLED);

        self::assertSame(Order::STATUS_CANCELLED, $updatedOrder->status);
        self::assertNotNull($updatedOrder->cancelled_at);
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(OrderCancelled::class, $dispatcher->events[0]);
    }
}

final class SalesOrderStateEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): void
    {
        $this->events[] = $event;
    }
}
