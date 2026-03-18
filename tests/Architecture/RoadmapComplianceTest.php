<?php

declare(strict_types=1);

/**
 * RoadmapComplianceTest - Enterprise Roadmap Priority 1-2 Governance
 *
 * Validates that all Enterprise Roadmap structural mandates are enforced:
 * - String identifiers in Interaction DTOs
 * - RecordInteractionAction delegates via CommandBus (no direct orchestration)
 */
test('interaction data uses string identifiers', function () {
    $class = new ReflectionClass(\App\Application\Interactions\DTOs\InteractionData::class);
    expect($class->getProperty('tenantId')->getType()->getName())->toBe('string');

    if ($class->hasProperty('productId')) {
        $type = $class->getProperty('productId')->getType();
        // Allow nullable string
        expect($type->getName())->toBe('string');
    }
});

test('record interaction command uses string identifiers', function () {
    $class = new ReflectionClass(\App\Application\Interactions\Commands\RecordInteractionCommand::class);
    expect($class->getProperty('tenantId')->getType()->getName())->toBe('string');

    if ($class->hasProperty('productId')) {
        $type = $class->getProperty('productId')->getType();
        expect($type->getName())->toBe('string');
    }
});

test('record interaction action delegates to bus', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Interactions/RecordInteractionAction.php');
    expect($content)->not->toContain('transaction(')
        ->not->toContain('->repository->')
        ->toContain('CommandBusInterface');
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
                expect($param->getType()->getName())->toBe('string');
            }
        }
    }
});
