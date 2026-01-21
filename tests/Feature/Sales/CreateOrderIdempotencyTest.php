<?php

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Decorators\IdempotentCreateOrderHandler;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Infrastructure\Common\Idempotency\IdempotencyService;

/**
 * ⚔️ TEST DE ATAQUE DE REPETICIÓN (Stress Test)
 * "Laws of Distributed Testing: Ley de Simulación de Carrera & Caja Negra"
 */
it('survives a synthetic replay attack ensuring single execution of core logic', function () {
    // 1. Mocking del Núcleo (The Inner Barrier)
    // El núcleo DEBE ser intocable después de la primera ejecución.
    $mockCore = Mockery::mock(CreateOrderHandlerInterface::class);

    // Generamos un resultado válido simulado
    $expectedResult = new CreateOrderResult(
        id: 999,
        orderNumber: 'ORD-ATOMIC-01',
        totalAmount: 1500.00,
        status: 'pending',
        itemsCount: 2,
        currency: 'HNL',
        items: []
    );

    // EXPECTATIVA CRÍTICA: shouldReceive(...)->once()
    // Si pest detecta una segunda llamada, el test falla.
    $mockCore->shouldReceive('handle')
        ->once() // <--- THE LAW OF SINGLE EXECUTION
        ->andReturn($expectedResult);

    // 2. Ensamblaje de la Defensa (The Onion)
    // Usamos ArrayStore para simular Redis en memoria con comportamiento real de Cache
    $store = new \Illuminate\Cache\ArrayStore;
    $repo = new \Illuminate\Cache\Repository($store);
    $service = new IdempotencyService($repo);

    $decorator = new IdempotentCreateOrderHandler($mockCore, $service);

    // 3. Command Setup
    $command = new CreateOrderCommand(
        tenantId: 1,
        customerId: 10,
        currency: 'HNL',
        items: [['product_id' => 1, 'quantity' => 1]],
        idempotencyKey: 'atomic-key-XYZ' // Clave explícita para el ataque
    );

    // 4. Ejecución del Ataque (Burst Fire)

    // Disparo 1 (Cache Miss -> Lock -> Core -> Store)
    $resultA = $decorator->handle($command);

    // Disparo 2 (Replay -> Cache Hit)
    $resultB = $decorator->handle($command);

    // Disparo 3 (Replay -> Cache Hit)
    $resultC = $decorator->handle($command);

    // 5. Aserción de Victoria (Consistency Check)
    // Verificamos que todos los resultados sean idénticos en estructura y valor
    expect($resultA)->toBeInstanceOf(CreateOrderResult::class)
        ->and($resultB)->toBeInstanceOf(CreateOrderResult::class)
        ->and($resultA->orderNumber)->toBe($expectedResult->orderNumber)
        ->and($resultB->orderNumber)->toBe($expectedResult->orderNumber)
        // Molecular Identity: Result objects are distinct instances but identical data
        ->and($resultA)->not->toBe($resultB) // Different objects (deserialized)
        ->and($resultA->toArray())->toBe($resultB->toArray()); // Same Data logic

});
