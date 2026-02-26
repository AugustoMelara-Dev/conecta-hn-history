<?php

declare(strict_types=1);

/**
 * SensitiveDataComplianceTest - PII Redaction Governance
 *
 * Enforces P1-REDACT: Password-carrying Commands must implement
 * SensitiveDataInterface to enable Outbox/Activity Log sanitization.
 */

use App\Application\Shared\Contracts\SensitiveDataInterface;

test('auth commands implement sensitive data interface', function () {
    $commands = [
        \App\Application\Auth\Commands\ResetUserPasswordCommand::class,
        \App\Application\Auth\Commands\UpdateUserPasswordCommand::class,
        \App\Application\Identity\Commands\RegisterUserCommand::class,
    ];

    foreach ($commands as $commandClass) {
        $reflection = new ReflectionClass($commandClass);

        expect($reflection->implementsInterface(SensitiveDataInterface::class))
            ->toBeTrue("Command $commandClass must implement SensitiveDataInterface");

        $instance = $reflection->newInstanceWithoutConstructor();
        $fields = $instance->getSensitiveProperties();

        $hasPasswordField = false;
        foreach ($fields as $field) {
            if (str_contains(strtolower($field), 'password')) {
                $hasPasswordField = true;
                break;
            }
        }
        expect($hasPasswordField)->toBeTrue("$commandClass must declare a password-related sensitive field");
    }
});
