<?php

declare(strict_types=1);

use App\Domain\Shared\Services\TenantContext;

/**
 * TenantContext UUID Support Tests
 *
 * INVARIANT: TenantContext must accept UUID strings natively
 * and initialize the Zero Trust context successfully.
 */
it('accepts uuid strings and initializes the zero trust context successfully', function () {
    $context = new TenantContext;

    $uuid = 'a127c64c-85df-4248-896d-6d9cf7be8e9c';
    $context->setTenant($uuid);

    expect($context->id())->toBe($uuid);
    expect($context->isInitialized())->toBeTrue();
});

it('still accepts integer ids for backwards compatibility', function () {
    $context = new TenantContext;

    $context->setTenant(42);

    expect($context->id())->toBe(42);
    expect($context->isInitialized())->toBeTrue();
});

it('prevents tenant hopping with uuid ids', function () {
    $context = new TenantContext;
    $context->setTenant('uuid-one');

    expect(fn () => $context->setTenant('uuid-two'))
        ->toThrow(\TypeError::class);
});
