<?php

declare(strict_types=1);

use App\Application\Shared\Contracts\TenantAware;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Application\Shared\Contracts\UpcasterInterface;
use App\Infrastructure\Bus\Serialization\CommandUpcaster;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * TenantContractTest - Unified Contract & Upcaster Governance
 *
 * Enforces:
 * 1. TenantAwareAuthenticatable extends both Authenticatable and TenantAware
 * 2. User model implements the unified contract
 * 3. CommandUpcaster service exists and functions correctly
 */
test('tenant aware authenticatable combines both contracts', function () {
    $reflection = new ReflectionClass(TenantAwareAuthenticatable::class);
    $interfaces = $reflection->getInterfaceNames();

    expect($interfaces)
        ->toContain(Authenticatable::class)
        ->toContain(TenantAware::class);
});

test('user model implements unified tenant aware authenticatable contract', function () {
    $user = new User;
    expect($user)->toBeInstanceOf(TenantAwareAuthenticatable::class);
    expect($user)->toBeInstanceOf(Authenticatable::class);
    expect($user)->toBeInstanceOf(TenantAware::class);
});

test('reset password action uses unified contract', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Authentication/ResetPasswordAction.php');
    expect($content)
        ->toContain('TenantAwareAuthenticatable')
        ->not()->toContain('use Illuminate\Contracts\Auth\Authenticatable;');
});

test('command upcaster service exists and is instantiable', function () {
    expect(class_exists(CommandUpcaster::class))->toBeTrue();

    $upcaster = new CommandUpcaster;
    expect($upcaster)->toBeInstanceOf(CommandUpcaster::class);
    expect($upcaster->count())->toBe(0);
});

test('command upcaster processes payloads through upcaster chain', function () {
    // Create a test upcaster: V1 → V2 adds 'upgraded' key
    $upcaster = new CommandUpcaster([
        new class implements UpcasterInterface
        {
            public function supports(string $eventType, int $schemaVersion): bool
            {
                return $eventType === 'test.event' && $schemaVersion === 1;
            }

            public function fromVersion(): int
            {
                return 1;
            }

            public function toVersion(): int
            {
                return 2;
            }

            public function upcast(array $payload): array
            {
                $payload['upgraded'] = true;

                return $payload;
            }
        },
    ]);

    $result = $upcaster->process([
        'event_type' => 'test.event',
        'schema_version' => 1,
        'data' => 'original',
    ]);

    expect($result)
        ->toHaveKey('upgraded', true)
        ->toHaveKey('schema_version', 2)
        ->toHaveKey('data', 'original');
});

test('command upcaster rejects payloads missing required keys', function () {
    $upcaster = new CommandUpcaster;
    $upcaster->process(['data' => 'no_type_or_version']);
})->throws(\InvalidArgumentException::class, 'event_type');
