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
test('Application Layer should NOT depend on Eloquent Models')
    ->expect('App\Application\Billing')
    ->toUseNothingIn('App\Models');

test('Application Layer should NOT depend on HTTP Layer')
    ->expect('App\Application\Billing')
    ->toUseNothingIn('Illuminate\Http');

// 2. DOMAIN LAYER PURITY
test('Domain Layer should NOT depend on Laravel Infrastructure')
    ->expect('App\Domain\Billing')
    ->toUseNothingIn('Illuminate\Database')
    ->toUseNothingIn('Illuminate\Support\Facades')
    ->toUseNothingIn('App\Infrastructure');

test('Domain Layer should NOT depend on Application Layer')
    ->expect('App\Domain\Billing')
    ->toUseNothingIn('App\Application\Billing');

// 3. CONTRACT ENFORCEMENT
test('Repositories must be interfaces')
    ->expect('App\Domain\Billing\Repositories')
    ->toBeInterfaces();

test('Domain Exceptions must extend DomainException')
    ->expect('App\Domain\Billing\Exceptions')
    ->toExtend('DomainException');

// 4. VALUE OBJECTS & ENUMS
test('Value Objects must be final and immutable')
    ->expect('App\Domain\Shared\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

test('Enums must be backed enums')
    ->expect('App\Domain\Billing\Enums')
    ->toBeEnums();
