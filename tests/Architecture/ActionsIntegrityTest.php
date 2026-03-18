<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;

/**
 * ActionsIntegrityTest - Governance Tests for Actions Layer Compliance
 *
 * Enforces:
 * 1. No Validator facade usage (must use injected Factory contract)
 * 2. String-typed identifiers in Domain DTOs (UUID/ULID safety)
 * 3. Correct namespace resolution for Command imports
 */
test('actions do not use validator facade', function () {
    expect('App\Infrastructure\Identity\Fortify\Actions')
        ->not->toUse(Validator::class);
    expect('App\Infrastructure\Interactions\Actions')
        ->not->toUse(Validator::class);
});

test('interaction data uses string for tenant identifier', function () {
    $class = new ReflectionClass(\App\Application\Interactions\DTOs\InteractionData::class);
    $tenantId = $class->getProperty('tenantId');
    expect($tenantId->getType()->getName())->toBe('string');
});

test('update user profile command namespace is correct', function () {
    $content = file_get_contents(__DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Identity/UpdateUserProfileInformation.php');
    expect($content)
        ->toContain('App\Application\Identity\Commands\UpdateUserProfileCommand')
        ->not->toContain('App\Application\Auth\Commands\UpdateUserProfileCommand');
});

test('interaction repository interface uses string for tenant identifier', function () {
    $class = new ReflectionClass(\App\Domain\Interactions\Repositories\InteractionRepositoryInterface::class);
    foreach ($class->getMethods() as $method) {
        foreach ($method->getParameters() as $param) {
            if ($param->getName() === 'tenantId') {
                expect($param->getType()->getName())->toBe('string');
            }
        }
    }
});

test('user loyalty balance accepts string tenant id', function () {
    $class = new ReflectionClass(\App\Models\User::class);
    foreach (['loyaltyBalance', 'activeLoyaltyBalance'] as $methodName) {
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);
            foreach ($method->getParameters() as $param) {
                if ($param->getName() === 'tenantId') {
                    expect($param->getType()->getName())->toBe('string');
                }
            }
        }
    }
});
