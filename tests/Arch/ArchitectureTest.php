<?php

declare(strict_types=1);

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURE GOVERNANCE TESTS - CI/CD Firewall                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  These tests enforce architectural rules. A failing test blocks deploy. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
test('controllers should not interact directly with models')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Models')
    ->ignoring([
        // ZERO EXEMPTIONS: All controllers must use repository/reader pattern
    ]);

test('controllers should not use debug functions')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

test('application code should not access env directly')
    ->expect('App')
    ->not->toUse('env');

test('domain interfaces should not depend on infrastructure')
    ->expect('App\Domain')
    ->not->toUse('App\Infrastructure');

test('controllers should be final')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()
    ->ignoring('App\Http\Controllers\Controller');

test('form requests should be final')
    ->expect('App\Http\Requests')
    ->classes()
    ->toBeFinal();

// ═══════════════════════════════════════════════════════════════════════════════
// APPLICATION LAYER ISOLATION TESTS (DIP Compliance)
// ═══════════════════════════════════════════════════════════════════════════════

test('handlers should not import eloquent models directly')
    ->expect('App\Application\Handlers')
    ->not->toUse('App\Models');

test('dtos should not use uploaded file objects')
    ->expect('App\Application\DTOs')
    ->not->toUse('Illuminate\Http\UploadedFile');

test('dtos should be final readonly classes')
    ->expect('App\Application\DTOs')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

test('commands should be final readonly classes')
    ->expect('App\Application\Commands')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

test('jobs should implement should queue interface')
    ->expect('App\Infrastructure\Jobs')
    ->classes()
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

// ═══════════════════════════════════════════════════════════════════════════════
// HEXAGONAL ARCHITECTURE BOUNDARY ENFORCEMENT
// ═══════════════════════════════════════════════════════════════════════════════

test('application layer should not depend on http layer')
    ->expect('App\Application')
    ->not->toUse('App\Http');

test('commands should not use uploaded file objects')
    ->expect('App\Application\Commands')
    ->not->toUse('Illuminate\Http\UploadedFile');

test('application layer should not use illuminate http request')
    ->expect('App\Application')
    ->not->toUse('Illuminate\Http\Request');

// ═══════════════════════════════════════════════════════════════════════════════
// LAW 1: APPLICATION LAYER ISOLATION GUARD (FRAMEWORK DECOUPLING)
// ═══════════════════════════════════════════════════════════════════════════════

test('application layer must not use db facade')
    ->expect('App\Application')
    ->not->toUse('Illuminate\Support\Facades\DB');

test('application layer must not use log facade')
    ->expect('App\Application')
    ->not->toUse('Illuminate\Support\Facades\Log');

test('application layer must not use cache facade')
    ->expect('App\Application')
    ->not->toUse('Illuminate\Support\Facades\Cache');

// ═══════════════════════════════════════════════════════════════════════════════
// LAW 2: UUID IDENTITY ENFORCEMENT GUARD (ASYNC SAFETY)
// ═══════════════════════════════════════════════════════════════════════════════

test('listeners should use string identifiers not integers')
    ->expect('App\Listeners')
    ->not->toUse(['toInt']);

test('domain events should serialize identifiers as strings')
    ->expect('App\Events')
    ->not->toUse(['toInt']);

// ═══════════════════════════════════════════════════════════════════════════════
// LAW 3: PORTS AND ADAPTERS EXISTENCE GUARD
// ═══════════════════════════════════════════════════════════════════════════════

test('transaction manager interface exists in application contracts')
    ->expect('App\Application\Shared\Contracts\TransactionManagerInterface')
    ->toBeInterface();

test('logger interface exists in application contracts')
    ->expect('App\Application\Shared\Contracts\LoggerInterface')
    ->toBeInterface();
