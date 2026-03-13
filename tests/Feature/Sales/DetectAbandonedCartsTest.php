<?php

use App\Application\Sales\Commands\DetectAbandonedCartsCommand;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Application\Sales\Handlers\DetectAbandonedCartsHandler;
use App\Domain\Sales\Events\CartAbandoned;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('handler fetches abandoned carts and dispatches domain events', function () {
    $portMock = Mockery::mock(CheckoutSessionPortInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('markAsAbandoned')
            ->once()
            ->with(30)
            ->andReturn([
                [
                    'id' => 10,
                    'tenant_id' => 'tenant-1',
                    'session_id' => 'session-123',
                    'user_id' => 99,
                    'subtotal' => 150.00,
                ],
            ]);
    });

    $dispatcherMock = Mockery::mock(EventDispatcherInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event) {
                return $event instanceof CartAbandoned
                    && $event->checkoutSessionId === 10
                    && $event->tenantId === 'tenant-1'
                    && $event->sessionId === 'session-123'
                    && $event->userId === 99
                    && $event->subtotal === 150.0;
            });
    });

    $handler = new DetectAbandonedCartsHandler($portMock, $dispatcherMock);
    $handler->handle(new DetectAbandonedCartsCommand(30));

    expect(true)->toBeTrue();
});
