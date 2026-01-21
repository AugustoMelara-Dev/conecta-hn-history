<?php

declare(strict_types=1);

namespace App\Domain\Identity\Upcasters;

use App\Domain\Common\EventUpcaster;
use App\Domain\Identity\Events\UserRegistered;

final class UserRegisteredV1ToV2Upcaster implements EventUpcaster
{
    public function supports(string $eventType, int $sourceVersion): bool
    {
        return $eventType === UserRegistered::class && $sourceVersion === 1;
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
}
