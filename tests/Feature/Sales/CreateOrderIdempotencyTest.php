<?php

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Decorators\IdempotentCreateOrderHandler;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;

/**
 * TEST DE ATAQUE DE REPETICION (Stress Test)
 * "Laws of Distributed Testing: Ley de Simulacion de Carrera y Caja Negra"
 */
it('survives a synthetic replay attack ensuring single execution of core logic', function () {
    $mockCore = Mockery::mock(CreateOrderHandlerInterface::class);

    $expectedResult = new CreateOrderResult(
        id: 999,
        orderNumber: 'ORD-ATOMIC-01',
        totalAmount: 1500.00,
        status: 'pending',
        itemsCount: 2,
        currency: 'HNL',
        items: []
    );

    $mockCore->shouldReceive('handle')
        ->once()
        ->andReturn($expectedResult);

    $lock = new class
    {
        private bool $released = false;

        public function get(): bool
        {
            return true;
        }

        public function release(): void
        {
            $this->released = true;
        }
    };

    $service = Mockery::mock(IdempotencyServiceInterface::class);
    $service->shouldReceive('get')
        ->times(4)
        ->with('atomic-key-XYZ')
        ->andReturn(null, null, $expectedResult, $expectedResult);
    $service->shouldReceive('lock')
        ->once()
        ->with('atomic-key-XYZ')
        ->andReturn($lock);
    $service->shouldReceive('store')
        ->once()
        ->with('atomic-key-XYZ', Mockery::type(CreateOrderResult::class));

    $decorator = new IdempotentCreateOrderHandler($mockCore, $service);

    $command = new CreateOrderCommand(
        tenantId: '1',
        customerId: '10',
        currency: 'HNL',
        items: [['product_id' => 1, 'quantity' => 1]],
        idempotencyKey: 'atomic-key-XYZ'
    );

    $resultA = $decorator->handle($command);
    $resultB = $decorator->handle($command);
    $resultC = $decorator->handle($command);

    expect($resultA)->toBeInstanceOf(CreateOrderResult::class)
        ->and($resultB)->toBeInstanceOf(CreateOrderResult::class)
        ->and($resultC)->toBeInstanceOf(CreateOrderResult::class)
        ->and($resultA->orderNumber)->toBe($expectedResult->orderNumber)
        ->and($resultB->orderNumber)->toBe($expectedResult->orderNumber)
        ->and($resultC->orderNumber)->toBe($expectedResult->orderNumber)
        ->and($resultA->toArray())->toBe($resultB->toArray())
        ->and($resultB->toArray())->toBe($resultC->toArray());
});





