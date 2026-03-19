<?php

declare(strict_types=1);

/**
 * RoadmapComplianceTest - Enterprise Roadmap Priority 1-2 Governance
 *
 * Validates that all Enterprise Roadmap structural mandates are enforced:
 * - String identifiers in Interaction DTOs
 * - RecordInteractionAction delegates via CommandBus (no direct orchestration)
 */
use ReflectionNamedType;

test('interaction data uses string identifiers', function () {
    $class = new ReflectionClass(\App\Application\Interactions\DTOs\InteractionData::class);
    $tenantIdType = $class->getProperty('tenantId')->getType();
    expect($tenantIdType)->toBeInstanceOf(ReflectionNamedType::class);
    expect($tenantIdType instanceof ReflectionNamedType ? $tenantIdType->getName() : null)->toBe('string');

    if ($class->hasProperty('productId')) {
        $type = $class->getProperty('productId')->getType();
        expect($type)->toBeInstanceOf(ReflectionNamedType::class);
        expect($type instanceof ReflectionNamedType ? $type->getName() : null)->toBe('string');
    }
});

test('record interaction command uses string identifiers', function () {
    $class = new ReflectionClass(\App\Application\Interactions\Commands\RecordInteractionCommand::class);
    $tenantIdType = $class->getProperty('tenantId')->getType();
    expect($tenantIdType)->toBeInstanceOf(ReflectionNamedType::class);
    expect($tenantIdType instanceof ReflectionNamedType ? $tenantIdType->getName() : null)->toBe('string');

    if ($class->hasProperty('productId')) {
        $type = $class->getProperty('productId')->getType();
        expect($type)->toBeInstanceOf(ReflectionNamedType::class);
        expect($type instanceof ReflectionNamedType ? $type->getName() : null)->toBe('string');
    }
});

test('record interaction action delegates to bus', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Interactions/RecordInteractionAction.php');
    expect(str_contains((string) $content, 'transaction('))->toBeFalse();
    expect(str_contains((string) $content, '->repository->'))->toBeFalse();
    expect($content)->toContain('CommandBusInterface');
});

test('orphaned reactivate user files are deleted', function () {
    // Verify orphaned User/ directory files are gone
    expect(file_exists(__DIR__.'/../../app/Application/User/Commands/ReactivateUserCommand.php'))->toBeFalse();
    expect(file_exists(__DIR__.'/../../app/Application/User/Handlers/ReactivateUserHandler.php'))->toBeFalse();
    // Verify orphaned UserLifecycle/ directory files are gone
    expect(file_exists(__DIR__.'/../../app/Application/UserLifecycle/Commands/ReactivateUserCommand.php'))->toBeFalse();
    expect(file_exists(__DIR__.'/../../app/Application/UserLifecycle/Handlers/ReactivateUserHandler.php'))->toBeFalse();
    // Verify consolidated into Identity/
    expect(file_exists(__DIR__.'/../../app/Application/Identity/Commands/ReactivateUserCommand.php'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../app/Application/Identity/Handlers/ReactivateUserHandler.php'))->toBeTrue();
});

test('analytics interaction repository interface uses string for tenant id', function () {
    $class = new ReflectionClass(\App\Domain\Analytics\Repositories\InteractionRepositoryInterface::class);
    foreach ($class->getMethods() as $method) {
        foreach ($method->getParameters() as $param) {
            if ($param->getName() === 'tenantId') {
                $type = $param->getType();
                expect($type)->toBeInstanceOf(ReflectionNamedType::class);
                expect($type instanceof ReflectionNamedType ? $type->getName() : null)->toBe('string');
            }
        }
    }
});
