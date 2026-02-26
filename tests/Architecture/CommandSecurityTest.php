<?php

declare(strict_types=1);

/**
 * CommandSecurityTest - SEC-01 & Idempotency Governance
 *
 * Enforces:
 * 1. All password-bearing Commands scrub passwords from stack traces.
 * 2. All state-mutating Commands implement IdempotentCommandInterface.
 */

test('password properties are marked as sensitive', function () {
    $commands = [
        \App\Application\Auth\Commands\ResetUserPasswordCommand::class,
        \App\Application\Auth\Commands\UpdateUserPasswordCommand::class,
        \App\Application\Identity\Commands\RegisterUserCommand::class,
    ];

    foreach ($commands as $command) {
        $reflection = new ReflectionClass($command);
        foreach ($reflection->getConstructor()->getParameters() as $param) {
            if (str_contains(strtolower($param->getName()), 'password')) {
                $attributes = $param->getAttributes(\SensitiveParameter::class);
                expect($attributes)->not->toBeEmpty(
                    "Parameter \${$param->getName()} in {$command} lacks #[SensitiveParameter]"
                );
            }
        }
    }
});

test('mutating commands implement idempotent interface', function () {
    $commands = [
        \App\Application\Auth\Commands\ResetUserPasswordCommand::class,
        \App\Application\Interactions\Commands\RecordInteractionCommand::class,
    ];

    foreach ($commands as $command) {
        $reflection = new ReflectionClass($command);
        expect($reflection->implementsInterface(
            \App\Application\Shared\Contracts\IdempotentCommandInterface::class
        ))->toBeTrue("Command {$command} must implement IdempotentCommandInterface");

        expect($reflection->hasMethod('getIdempotencyKey'))->toBeTrue();
    }
});

test('command hash does not leak password derivatives', function () {
    $content = file_get_contents(
        __DIR__ . '/../../app/Application/Auth/Commands/UpdateUserPasswordCommand.php'
    );

    expect($content)->not->toContain('md5(');
    expect($content)->not->toContain('sha1(');
    expect($content)->not->toContain('->newPassword)');
    expect($content)->not->toContain('->currentPassword)');
});
