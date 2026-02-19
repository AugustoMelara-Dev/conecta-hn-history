<?php

declare(strict_types=1);

/**
 * SharedKernelTest - Microservices Extraction Readiness
 *
 * Enforces:
 * - ISOLATION-01: Shared Kernel must not depend on any Bounded Context
 * - ISOLATION-02: Shared Kernel must be self-contained (Domain + Framework only)
 *
 * The Shared Kernel is the primary impediment to microservices extraction.
 * These tests ensure unidirectional dependency flow:
 *   Contexts → Shared ✅
 *   Shared → Contexts ❌
 */

test('shared kernel does not depend on bounded contexts')
    ->expect('App\Application\Shared')
    ->not->toUse([
        'App\Application\Auth',
        'App\Application\Billing',
        'App\Application\Identity',
        'App\Application\Interactions',
        'App\Application\Payments',
        'App\Application\Products',
        'App\Application\Reviews',
        'App\Application\Sales',
        'App\Application\Tenant',
        'App\Application\User',          // Legacy path
        'App\Application\UserLifecycle', // Legacy path
    ]);

test('shared kernel is self contained')
    ->expect('App\Application\Shared')
    ->toUseNothing()
    ->ignoring([
        'App\Application\Shared', // Self usage allowed
        'App\Domain',             // Domain contracts allowed
        'App\Infrastructure',     // Adapter bridge (strictly controlled)
        'Illuminate',
        'Ramsey\Uuid',
        'Carbon',
        'Psr',
    ]);
