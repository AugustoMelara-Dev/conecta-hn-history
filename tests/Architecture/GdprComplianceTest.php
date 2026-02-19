<?php

declare(strict_types=1);

/**
 * GdprComplianceTest - PII Redaction Enforcement
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GDPR Art. 4: IP Addresses and User-Agents are PII.                   ║
 * ║  Commands carrying PII must implement SensitiveDataInterface and use   ║
 * ║  #[SensitiveParameter] to prevent leakage in logs, traces & Outbox.   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Shared\Contracts\SensitiveDataInterface;

test('RecordInteractionCommand redacts GDPR PII fields', function () {
    $reflection = new ReflectionClass(RecordInteractionCommand::class);

    // 1. Verify Interface Implementation (for Outbox/Logs)
    expect($reflection->implementsInterface(SensitiveDataInterface::class))
        ->toBeTrue('RecordInteractionCommand must implement SensitiveDataInterface for GDPR compliance.');

    $command = $reflection->newInstanceWithoutConstructor();
    expect($command->getSensitiveProperties())
        ->toContain('ipAddress')
        ->toContain('userAgent');

    // 2. Verify #[SensitiveParameter] Attribute (for Stack Traces)
    $constructor = $reflection->getConstructor();
    $piiParams = ['ipAddress', 'userAgent'];

    foreach ($constructor->getParameters() as $param) {
        if (in_array($param->getName(), $piiParams, true)) {
            $attributes = $param->getAttributes(\SensitiveParameter::class);
            expect($attributes)
                ->not->toBeEmpty("Constructor argument [{$param->getName()}] must have #[SensitiveParameter] attribute.");
        }
    }
});
