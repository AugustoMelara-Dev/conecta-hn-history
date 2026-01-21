<?php

use App\Application\Identity\Results\RegisterUserResult;
use App\Infrastructure\Common\Idempotency\IdempotencyService;

/**
 * 🧪 TEST UNITARIO DE INTEGRIDAD DE SERIALIZACIÓN
 * "Laws of Distributed Testing: Ley de Integridad de Payload (The Bitwise Check)"
 */
it('guarantees bitwise serialization integrity for app DTOs', function () {
    // 1. Configuración del Entorno (Mock de Caché Array en Memoria)
    $store = new \Illuminate\Cache\ArrayStore;
    $repository = new \Illuminate\Cache\Repository($store);

    $service = new IdempotencyService($repository);

    // 2. Preparación del Sujeto (Real App DTO)
    // Usamos RegisterUserResult que ya implementa toArray/fromArray
    $original = new RegisterUserResult(
        id: 99999,
        tenantId: 500,
        email: 'stress_test@titanium.io',
        requiresVerification: true
    );

    $key = 'serialization_integrity_test_real';

    // 3. Persistencia Simulada
    $service->store($key, $original);

    // 4. Recuperación Forense
    $restored = $service->get($key);

    // 5. Aserción de Identidad (Molecular Check)
    expect($restored)->not->toBeNull()
        ->and($restored)->toBeInstanceOf(RegisterUserResult::class)
        ->and($restored)->not->toBe($original) // DEBE ser una nueva instancia (Memoria separada)
        ->and($restored->id)->toBe($original->id)
        ->and($restored->tenantId)->toBe($original->tenantId)
        ->and($restored->email)->toBe($original->email)
        ->and($restored->requiresVerification)->toBe($original->requiresVerification);

    // Double Check array transformation
    expect($restored->toArray())->toBe($original->toArray());
});

it('rejects garbage payloads without crashing', function () {
    $store = new \Illuminate\Cache\ArrayStore;
    $repository = new \Illuminate\Cache\Repository($store);
    $service = new IdempotencyService($repository);

    // Inyectamos basura manualmente para simular corrupción
    $repository->put('idempotency_result:garbage_key', 'NOT_JSON_DATA', 60);

    $result = $service->get('garbage_key');

    expect($result)->toBeNull();
});

it('ignores payloads with missing class or data keys', function () {
    $store = new \Illuminate\Cache\ArrayStore;
    $repository = new \Illuminate\Cache\Repository($store);
    $service = new IdempotencyService($repository);

    // JSON válido pero sin la estructura esperada
    $payload = json_encode(['foo' => 'bar']);
    $repository->put('idempotency_result:incomplete_key', $payload, 60);

    $result = $service->get('incomplete_key');

    expect($result)->toBeNull();
});
