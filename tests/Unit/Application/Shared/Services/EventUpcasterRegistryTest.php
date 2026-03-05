<?php

declare(strict_types=1);

use App\Application\Shared\Contracts\UpcasterInterface;
use App\Application\Shared\Services\EventUpcasterRegistry;

it('sequentially upcasts an event payload from v1 to v3', function () {
    $upcasterV1toV2 = new class implements UpcasterInterface
    {
        public function supports(string $eventType, int $schemaVersion): bool
        {
            return $eventType === 'TestEvent' && $schemaVersion === 1;
        }

        public function upcast(array $payload): array
        {
            $payload['name'] = $payload['first_name'].' '.$payload['last_name'];
            unset($payload['first_name'], $payload['last_name']);

            return $payload;
        }

        public function fromVersion(): int
        {
            return 1;
        }

        public function toVersion(): int
        {
            return 2;
        }
    };

    $upcasterV2toV3 = new class implements UpcasterInterface
    {
        public function supports(string $eventType, int $schemaVersion): bool
        {
            return $eventType === 'TestEvent' && $schemaVersion === 2;
        }

        public function upcast(array $payload): array
        {
            $payload['full_name'] = $payload['name'];
            unset($payload['name']);

            return $payload;
        }

        public function fromVersion(): int
        {
            return 2;
        }

        public function toVersion(): int
        {
            return 3;
        }
    };

    $registry = new EventUpcasterRegistry([$upcasterV1toV2, $upcasterV2toV3]);

    $initialPayload = [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ];

    $result = $registry->upcast('TestEvent', 1, $initialPayload);

    expect($result['version'])->toBe(3)
        ->and($result['payload'])->toHaveKey('full_name', 'John Doe')
        ->and($result['payload'])->not->toHaveKey('first_name')
        ->and($result['payload'])->not->toHaveKey('name');
});

it('throws an exception if there is a gap in the upcasting chain', function () {
    $faultyUpcaster = new class implements UpcasterInterface
    {
        public function supports(string $eventType, int $schemaVersion): bool
        {
            return $eventType === 'TestEvent'; // Claims to support regardless of version
        }

        public function upcast(array $payload): array
        {
            return $payload;
        }

        public function fromVersion(): int
        {
            return 2; // Gap: starts at 2 but caller passes version 1
        }

        public function toVersion(): int
        {
            return 3;
        }
    };

    $registry = new EventUpcasterRegistry([$faultyUpcaster]);

    expect(fn () => $registry->upcast('TestEvent', 1, []))
        ->toThrow(RuntimeException::class, 'Upcasting gap detected');
});
