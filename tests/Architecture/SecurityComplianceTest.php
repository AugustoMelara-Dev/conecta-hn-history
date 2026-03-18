<?php

declare(strict_types=1);

/**
 * SecurityComplianceTest - PII Protection & Tenant Integrity Governance
 *
 * Enforces:
 * - SEC-01: All password-carrying Command parameters use #[SensitiveParameter]
 * - SEC-02: CreateNewUser validates tenant_id existence against the tenants table
 *
 * These are non-negotiable security invariants that prevent:
 * - Password leakage in stack traces, logs, and debug output
 * - Ghost tenant references creating orphaned user accounts
 */
test('commands handling passwords use sensitive parameter attribute', function () {
    $commands = [
        \App\Application\Identity\Commands\RegisterUserCommand::class,
        \App\Application\Auth\Commands\ResetUserPasswordCommand::class,
        \App\Application\Auth\Commands\UpdateUserPasswordCommand::class,
    ];

    foreach ($commands as $commandClass) {
        $reflection = new ReflectionClass($commandClass);
        $constructor = $reflection->getConstructor();

        expect($constructor)->not->toBeNull("{$commandClass} must have a constructor");

        foreach ($constructor->getParameters() as $param) {
            if (str_contains(strtolower($param->getName()), 'password')) {
                $attributes = $param->getAttributes(\SensitiveParameter::class);
                expect($attributes)
                    ->not->toBeEmpty("\${$param->getName()} in {$commandClass} must have #[SensitiveParameter]");
            }
        }
    }
});

test('create new user action validates tenant existence', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Identity/CreateNewUser.php');
    expect($content)
        ->toContain("'tenant_id'")
        ->toContain('exists:tenants,id');
});

test('all password-carrying commands are immutable', function () {
    $commands = [
        \App\Application\Identity\Commands\RegisterUserCommand::class,
        \App\Application\Auth\Commands\ResetUserPasswordCommand::class,
        \App\Application\Auth\Commands\UpdateUserPasswordCommand::class,
    ];

    foreach ($commands as $commandClass) {
        $reflection = new ReflectionClass($commandClass);
        expect($reflection->isFinal())->toBeTrue("{$commandClass} must be final");
        expect($reflection->isReadOnly())->toBeTrue("{$commandClass} must be readonly");
    }
});
