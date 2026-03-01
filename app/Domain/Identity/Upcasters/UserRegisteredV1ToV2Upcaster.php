<?php

declare(strict_types=1);

namespace App\Domain\Identity\Upcasters;

use App\Application\Shared\Contracts\UpcasterInterface;
use App\Domain\Identity\Events\UserRegistered;

final class UserRegisteredV1ToV2Upcaster implements UpcasterInterface
{
    public function supports(string $eventType, int $schemaVersion): bool
    {
        return $eventType === UserRegistered::class && $schemaVersion === 1;
    }

    public function upcast(array $payload): array
    {
        // Transformation Logic: Split fullname into first_name and last_name
        $fullName = $payload['fullname'] ?? '';

        // cleanup old field
        unset($payload['fullname']);

        // Separate logically
        $parts = explode(' ', $fullName, 2);

        $payload['first_name'] = $parts[0] ?? '';
        $payload['last_name'] = $parts[1] ?? '';

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
}
