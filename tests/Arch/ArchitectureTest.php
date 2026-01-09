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
