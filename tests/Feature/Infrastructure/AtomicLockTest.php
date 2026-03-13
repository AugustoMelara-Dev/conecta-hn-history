<?php

use Illuminate\Support\Facades\Cache;

/**
 * TEST DE INTEGRACION DE BLOQUEO ATOMICO
 * "Laws of Distributed Testing: Ley de Simulacion de Carrera"
 *
 * Verifica exclusion mutua mediante Laravel Cache locks (ArrayStore).
 * No depende de AtomicIdempotencyService (DB-based): ese contrato
 * opera sobre processed_commands, no sobre cache locks.
 */
it('enforces exclusion when a lock is actively held', function () {
    // 1. Entorno: ArrayStore con soporte nativo de locks atomicos
    $resourceKey = 'critical_transaction_REF_500';

    // 2. Simulacion de Ocupacion (Proceso A)
    $lockProcessA = Cache::store('array')->lock($resourceKey);
    $acquiredA = $lockProcessA->get();

    expect($acquiredA)->toBeTrue('Process A failed to acquire initial lock');

    // 3. Intento de Invasion (Proceso B)
    $lockProcessB = Cache::store('array')->lock($resourceKey);
    $acquiredB = $lockProcessB->get();

    // 4. Asercion de Bloqueo
    expect($acquiredB)->toBeFalse('Process B breached the atomic lock! Race condition possible.');

    // 5. Liberacion y Recuperacion
    $lockProcessA->release();

    // Ahora C deberia poder entrar
    $lockProcessC = Cache::store('array')->lock($resourceKey);
    $acquiredC = $lockProcessC->get();

    expect($acquiredC)->toBeTrue('Process C could not acquire lock after release');

    $lockProcessC->release();
});
