<?php

declare(strict_types=1);

/**
 * ApplicationLayerTest - Inward Dependency Rule Enforcement
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INVARIANT A: Application Layer must never use Framework Facades.      ║
 * ║  INVARIANT B: Application Layer must never import App\Infrastructure.  ║
 * ║  INVARIANT C: Application Layer must never use Service Locator/Globals.║
 * ║  INVARIANT D: Handlers must be final readonly (PHP 8.2+).             ║
 * ║  INVARIANT E: Validators must be final.                               ║
 * ║  INVARIANT F: No Presentation Layer attributes (OpenAPI, etc).        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT A: Zero Facade Tolerance
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not use facades')
    ->expect('App\Application')
    ->not->toUse('Illuminate\Support\Facades');

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT B: No Concrete Infrastructure Dependency
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not depend on concrete infrastructure')
    ->expect('App\Application')
    ->not->toUse('App\Infrastructure');

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT C: No Service Locator or Global State
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not use service locator or global state')
    ->expect('App\Application')
    ->not->toUse([
        'app',
        'resolve',
        'request',
        'config',
        'env',
        'dd',
        'dump',
    ]);

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT D: Handler Taxonomy (Immutability + Composition)
// ═══════════════════════════════════════════════════════════════════════════

arch('handlers are final and readonly')
    ->expect('App\Application\*\Handlers')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT E: Validator Taxonomy (Sealed Classes)
// ═══════════════════════════════════════════════════════════════════════════

arch('validators are final')
    ->expect('App\Application\*\Validators')
    ->classes()
    ->toBeFinal();

// ---------------------------------------------------------------------------
// INVARIANT F: No Presentation Layer Attributes (OpenAPI, Swagger, etc.)
// ---------------------------------------------------------------------------

test('application layer does not contain presentation documentation attributes')
    ->expect('App\Application')
    ->not->toUse('OpenApi');
