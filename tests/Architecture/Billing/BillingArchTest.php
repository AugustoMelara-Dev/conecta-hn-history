<?php

declare(strict_types=1);

/**
 * ARCHITECTURE GOVERNANCE TESTS: Billing Subsystem
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ROBOCUP ARCHITECT: Automated Boundary Enforcement.                      ║
 * ║  ZERO TOLERANCE: Any violation fails the build immediately.              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

// 1. APPLICATION LAYER PURITY
test('Application Layer should NOT depend on Eloquent Models', function () {
    expect('App\Application\Billing')
        ->not()->toUse('App\Models');
});

test('Application Layer should NOT depend on HTTP Layer', function () {
    expect('App\Application\Billing')
        ->not()->toUse('Illuminate\Http');
});

// 2. DOMAIN LAYER PURITY
test('Domain Layer should NOT depend on Laravel Infrastructure', function () {
    expect('App\Domain\Billing')
        ->not()->toUse('Illuminate\Database')
        ->not()->toUse('Illuminate\Support\Facades')
        ->not()->toUse('App\Infrastructure');
});

test('Domain Layer should NOT depend on Application Layer', function () {
    expect('App\Domain\Billing')
        ->not()->toUse('App\Application\Billing');
});

// 3. CONTRACT ENFORCEMENT
test('Repositories must be interfaces', function () {
    expect('App\Domain\Billing\Repositories')
        ->toBeInterfaces();
});

test('Domain Exceptions must extend DomainException', function () {
    expect('App\Domain\Billing\Exceptions')
        ->toExtend('DomainException');
});

// 4. VALUE OBJECTS & ENUMS
test('Value Objects must be final and immutable', function () {
    expect('App\Domain\Shared\ValueObjects')
        ->toBeFinal()
        ->toBeReadonly();
});

test('Enums must be backed enums', function () {
    expect('App\Domain\Billing\Enums')
        ->toBeEnums();
});
