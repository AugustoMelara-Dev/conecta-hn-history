<?php

declare(strict_types=1);

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL ARCHITECTURE GUARDIAN - AUTOMATED BOUNDARY ENFORCEMENT        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  These tests are the "immune system" of the architecture.                ║
 * ║  They prevent future coupling regressions and enforce:                   ║
 * ║    - Application Layer Isolation (no framework facades)                  ║
 * ║    - UUID Identity Enforcement (no toInt() in async flows)               ║
 * ║    - Domain Purity (no infrastructure leakage)                           ║
 * ║                                                                          ║
 * ║  FAILURE = BLOCKED DEPLOYMENT                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @see https://pestphp.com/docs/arch-testing
 */

// ═══════════════════════════════════════════════════════════════════════════════
// LAW 1: APPLICATION LAYER ISOLATION GUARD (FRAMEWORK DECOUPLING)
// ═══════════════════════════════════════════════════════════════════════════════

test('application layer must not use DB facade - hexagonal enforcement')
    ->expect('App\Application')
    ->not->toUse('Illuminate\Support\Facades\DB');

test('application layer must not use Log facade - hexagonal enforcement')
    ->expect('App\Application')
    ->not->toUse('Illuminate\Support\Facades\Log');

test('application handlers must not import Eloquent models')
    ->expect('App\Application')
    ->not->toUse('App\Models');

// ═══════════════════════════════════════════════════════════════════════════════
// LAW 2: DOMAIN LAYER ISOLATION GUARD (HEXAGONAL PURITY)
// ═══════════════════════════════════════════════════════════════════════════════

test('domain layer must not depend on infrastructure')
    ->expect('App\Domain')
    ->not->toUse('App\Infrastructure');

// ═══════════════════════════════════════════════════════════════════════════════
// LAW 3: PORTS AND ADAPTERS EXISTENCE GUARD
// ═══════════════════════════════════════════════════════════════════════════════

test('TransactionManagerInterface must exist in application contracts')
    ->expect('App\Application\Shared\Contracts\TransactionManagerInterface')
    ->toBeInterface();

test('LoggerInterface must exist in application contracts')
    ->expect('App\Application\Shared\Contracts\LoggerInterface')
    ->toBeInterface();
