<?php

use App\Contracts\InventoryManagerInterface;
use App\Domain\Sales\Events\OrderCancelled;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Tenant;
use App\Services\OrderStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('order state service transitions order and prevents isPaid method crash', function () {
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

    $inventoryManagerMock = Mockery::mock(InventoryManagerInterface::class);
    $this->app->instance(InventoryManagerInterface::class, $inventoryManagerMock);

    $dispatcherMock = Mockery::mock(EventDispatcherInterface::class);
    $dispatcherMock->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::type(OrderCancelled::class));
    $this->app->instance(EventDispatcherInterface::class, $dispatcherMock);

    /** @var OrderStateService $service */
    $service = app(OrderStateService::class);

    $updatedOrder = $service->transition($order, Order::STATUS_CANCELLED);

    expect($updatedOrder->status)->toBe(Order::STATUS_CANCELLED)
        ->and($updatedOrder->cancelled_at)->not->toBeNull();
});
