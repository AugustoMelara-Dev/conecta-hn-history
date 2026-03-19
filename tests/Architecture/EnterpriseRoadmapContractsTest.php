<?php

declare(strict_types=1);

use ReflectionNamedType;

/**
 * EnterpriseRoadmapContractsTest - P0-P3 Interface Governance
 *
 * Validates that all Enterprise Roadmap foundational contracts exist
 * and satisfy their structural requirements.
 */
test('enterprise roadmap interfaces are defined', function () {
    $interfaces = [
        \App\Application\Shared\Contracts\SensitiveDataInterface::class,
        \App\Application\Identity\Contracts\UserUniquenessChecker::class,
        \App\Application\Identity\Contracts\UserReadProjection::class,
    ];

    foreach ($interfaces as $interface) {
        expect(interface_exists($interface))->toBeTrue("Interface {$interface} must exist.");
    }
});

test('sensitive data interface requires getSensitiveProperties method', function () {
    $reflection = new ReflectionClass(\App\Application\Shared\Contracts\SensitiveDataInterface::class);
    $method = $reflection->getMethod('getSensitiveProperties');
    $returnType = $method->getReturnType();

    expect($returnType instanceof ReflectionNamedType ? $returnType->getName() : null)->toBe('array');
});

test('user uniqueness checker requires isUnique method with correct signature', function () {
    $reflection = new ReflectionClass(\App\Application\Identity\Contracts\UserUniquenessChecker::class);
    $method = $reflection->getMethod('isUnique');
    $returnType = $method->getReturnType();

    expect($returnType instanceof ReflectionNamedType ? $returnType->getName() : null)->toBe('bool');
    expect($method->getNumberOfParameters())->toBe(4);
});

test('user read projection requires getById method', function () {
    $reflection = new ReflectionClass(\App\Application\Identity\Contracts\UserReadProjection::class);
    $method = $reflection->getMethod('getById');

    expect($method->getNumberOfParameters())->toBe(2);
});
