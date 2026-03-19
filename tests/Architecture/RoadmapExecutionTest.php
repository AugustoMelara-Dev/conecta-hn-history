<?php

declare(strict_types=1);

/**
 * RoadmapExecutionTest - Enterprise Roadmap Governance
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  P0: Persistence Decoupling — Commands carry scalars, not models.      ║
 * ║  P1: Governance Hardening — No stale ignoring() clauses.               ║
 * ║  P2: API Uniformity — Custom Actions use execute(), not handle().      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
test('P0: reset user password command uses scalar identity', function () {
    $class = new ReflectionClass(\App\Application\Auth\Commands\ResetUserPasswordCommand::class);
    expect($class->hasProperty('userId'))->toBeTrue();
    expect($class->hasProperty('user'))->toBeFalse();
});

test('P1: actions layer test has no stale ignores', function () {
    $content = file_get_contents(__DIR__.'/../../tests/Architecture/ActionsLayerTest.php');
    expect($content)->not()->toContain('ResetPasswordAction');
});

test('P2: custom actions use execute method', function () {
    $actions = [
        \App\Infrastructure\Interactions\Actions\RecordInteractionAction::class,
    ];

    foreach ($actions as $action) {
        $reflection = new ReflectionClass($action);
        expect($reflection->hasMethod('execute'))->toBeTrue("{$action} should have execute method");
        expect($reflection->hasMethod('handle'))->toBeFalse("{$action} should not have handle method");
    }
});
