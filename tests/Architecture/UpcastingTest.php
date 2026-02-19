<?php

declare(strict_types=1);

/**
 * UpcastingTest - P4 Event Upcasting Verification
 *
 * Validates:
 * - UpcasterInterface exists at canonical path with correct methods
 * - The upcasting pattern correctly transforms v1 → v2 payloads
 * - Upcasters correctly report support/non-support
 * - CommandUpcasterRegistry applies upcasters sequentially (V1→V2→V3)
 * - Registry passes through payloads when no upcasters match
 */

use App\Application\Shared\Contracts\UpcasterInterface;
use App\Infrastructure\Bus\Upcasting\CommandUpcasterRegistry;

test('upcaster interface exists at canonical path', function () {
    expect(interface_exists(UpcasterInterface::class))->toBeTrue();

    $reflection = new ReflectionClass(UpcasterInterface::class);
    $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

    expect($methods)
        ->toContain('supports')
        ->toContain('upcast')
        ->toContain('fromVersion')
        ->toContain('toVersion');
});

test('upcaster transforms v1 payload to v2', function () {
    // Inline implementation of UpcasterInterface for pattern proof
    $upcaster = new class implements UpcasterInterface {
        public function supports(string $eventType, int $schemaVersion): bool
        {
            return $eventType === 'user.registered' && $schemaVersion === 1;
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
            $payload['first_name'] = $payload['name'];
            $payload['last_name'] = 'Unknown';
            unset($payload['name']);
            return $payload;
        }
    };

    // v1 payload
    $v1 = ['name' => 'John', 'version' => 1];

    expect($upcaster->supports('user.registered', 1))->toBeTrue();
    expect($upcaster->supports('user.registered', 2))->toBeFalse();
    expect($upcaster->toVersion())->toBe(2);

    $v2 = $upcaster->upcast($v1);

    expect($v2)
        ->toHaveKey('first_name', 'John')
        ->toHaveKey('last_name', 'Unknown')
        ->not->toHaveKey('name');
});

// ─────────────────────────────────────────────────────────────────────────────
// CommandUpcasterRegistry Tests
// ─────────────────────────────────────────────────────────────────────────────

test('command upcaster registry sequentially upgrades payload V1→V2→V3', function () {
    $commandClass = 'App\\Application\\Identity\\Commands\\RegisterUserCommand';

    // V1→V2: Adds 'phoneNumber'
    $v1ToV2 = new class($commandClass) implements UpcasterInterface {
        public function __construct(private string $target) {}
        public function supports(string $eventType, int $schemaVersion): bool
        {
            return $eventType === $this->target && $schemaVersion === 1;
        }
        public function fromVersion(): int { return 1; }
        public function toVersion(): int { return 2; }
        public function upcast(array $payload): array
        {
            $payload['phoneNumber'] = '000-000-0000';
            return $payload;
        }
    };

    // V2→V3: Splits 'name' into 'firstName' + 'lastName'
    $v2ToV3 = new class($commandClass) implements UpcasterInterface {
        public function __construct(private string $target) {}
        public function supports(string $eventType, int $schemaVersion): bool
        {
            return $eventType === $this->target && $schemaVersion === 2;
        }
        public function fromVersion(): int { return 2; }
        public function toVersion(): int { return 3; }
        public function upcast(array $payload): array
        {
            $parts = explode(' ', $payload['name'] ?? 'Unknown');
            $payload['firstName'] = $parts[0];
            $payload['lastName'] = $parts[1] ?? '';
            unset($payload['name']);
            return $payload;
        }
    };

    $registry = new CommandUpcasterRegistry([$v2ToV3, $v1ToV2]); // Intentionally out-of-order

    $legacyPayload = [
        'tenantId' => '123',
        'email' => 'test@example.com',
        'name' => 'Jane Doe',
    ];

    $result = $registry->process($commandClass, $legacyPayload, 1);

    expect($result)
        ->toHaveKey('tenantId', '123')
        ->toHaveKey('email', 'test@example.com')
        ->toHaveKey('phoneNumber', '000-000-0000')
        ->toHaveKey('firstName', 'Jane')
        ->toHaveKey('lastName', 'Doe')
        ->not->toHaveKey('name');
});

test('command upcaster registry passes through when no upcasters match', function () {
    $registry = new CommandUpcasterRegistry([]);

    $payload = ['id' => '456', 'type' => 'test'];
    $result = $registry->process('App\\NonExistent\\Command', $payload, 1);

    expect($result)->toEqual($payload);
});

test('command upcaster registry resolves latest version correctly', function () {
    $commandClass = 'App\\Application\\Test\\Commands\\DummyCommand';

    $v1ToV2 = new class($commandClass) implements UpcasterInterface {
        public function __construct(private string $target) {}
        public function supports(string $eventType, int $schemaVersion): bool
        {
            return $eventType === $this->target && $schemaVersion === 1;
        }
        public function fromVersion(): int { return 1; }
        public function toVersion(): int { return 2; }
        public function upcast(array $payload): array { return $payload; }
    };

    $v2ToV3 = new class($commandClass) implements UpcasterInterface {
        public function __construct(private string $target) {}
        public function supports(string $eventType, int $schemaVersion): bool
        {
            return $eventType === $this->target && $schemaVersion === 2;
        }
        public function fromVersion(): int { return 2; }
        public function toVersion(): int { return 3; }
        public function upcast(array $payload): array { return $payload; }
    };

    $registry = new CommandUpcasterRegistry([$v1ToV2, $v2ToV3]);

    expect($registry->resolveLatestVersion($commandClass, 1))->toBe(3);
    expect($registry->resolveLatestVersion($commandClass, 2))->toBe(3);
    expect($registry->resolveLatestVersion($commandClass, 3))->toBe(3); // No upcaster for v3
});

