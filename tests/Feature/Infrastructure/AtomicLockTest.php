<?php

/**
 * 🔓 TEST DE INTEGRACIÓN DE BLOQUEO ATÓMICO
 * "Laws of Distributed Testing: Ley de Simulación de Carrera"
 */
it('enforces exclusion when a lock is actively held', function () {
    // 1. Entorno: Usamos ArrayStore que soporta locks atómicos simulados
    $store = new \Illuminate\Cache\ArrayStore;
    $repo = new \Illuminate\Cache\Repository($store);
    $service = new IdempotencyService($repo);

    $resourceKey = 'critical_transaction_REF_500';

    // 2. Simulación de Ocupación (Proceso A)
    // Adquirimos el lock manualmente.
    $lockProcessA = $service->lock($resourceKey);
    $acquiredA = $lockProcessA->get();

    expect($acquiredA)->toBeTrue('Process A failed to acquire initial lock');

    // 3. Intento de Invasión (Proceso B)
    // Intentamos adquirir el MISMO lock mientras A lo tiene.
    $lockProcessB = $service->lock($resourceKey);
    $acquiredB = $lockProcessB->get();

    // 4. Aserción de Bloqueo
    expect($acquiredB)->toBeFalse('Process B breached the atomic lock! Race condition possible.');

    // 5. Liberación y Recuperación
    $lockProcessA->release();

    // Ahora C debería poder entrar
    $lockProcessC = $service->lock($resourceKey);
    $acquiredC = $lockProcessC->get();

    expect($acquiredC)->toBeTrue('Process C could not acquire lock after release');

    $lockProcessC->release();
});
