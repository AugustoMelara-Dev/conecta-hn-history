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

test('application layer does not use facades', function () {
    expect('App\Application')
        ->not->toUse('Illuminate\Support\Facades');
});

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT B: No Concrete Infrastructure Dependency
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not depend on concrete infrastructure', function () {
    expect('App\Application')
        ->not->toUse('App\Infrastructure');
});

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT C: No Service Locator or Global State
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not use service locator or global state', function () {
    expect('App\Application')
        ->not->toUse([
            'app',
            'resolve',
            'request',
            'config',
            'env',
            'dd',
            'dump',
        ]);
});

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT D: Handler Taxonomy (Immutability + Composition)
// ═══════════════════════════════════════════════════════════════════════════

test('handlers are final and readonly', function () {
    expect('App\Application\*\Handlers')
        ->classes()
        ->toBeFinal()
        ->toBeReadonly();
});

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT E: Validator Taxonomy (Sealed Classes)
// ═══════════════════════════════════════════════════════════════════════════

test('validators are final', function () {
    expect('App\Application\*\Validators')
        ->classes()
        ->toBeFinal();
});

// ---------------------------------------------------------------------------
// INVARIANT F: No Presentation Layer Attributes (OpenAPI, Swagger, etc.)
// ---------------------------------------------------------------------------

test('application layer does not contain presentation documentation attributes', function () {
    expect('App\Application')
        ->not->toUse('OpenApi');
});
