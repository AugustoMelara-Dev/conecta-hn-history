<?php

use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Decorators\IdempotentCreateOrderHandler;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;

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
    $memory = [];
    $service = new class($memory) implements IdempotencyServiceInterface
    {
        public function __construct(private array &$memory) {}

        public function get(string $key): mixed
        {
            return $this->memory[$key] ?? null;
        }

        public function getState(string $key): ?string
        {
            return array_key_exists($key, $this->memory) ? 'COMPLETED' : null;
        }

        public function registerProcessing(string $key, int $ttlSeconds = 60): void {}

        public function store(string $key, mixed $value, int $ttlSeconds = 86400): void
        {
            $this->memory[$key] = $value;
        }
    };

    $decorator = new IdempotentCreateOrderHandler($mockCore, $service);

    // 3. Command Setup
    $command = new CreateOrderCommand(
        tenantId: '00000000-0000-4000-8000-000000000001',
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
        // In-memory idempotency test double may return same object reference.
        ->and($resultA)->toBe($resultB)
        ->and($resultA->toArray())->toBe($resultB->toArray()); // Same Data logic

});
