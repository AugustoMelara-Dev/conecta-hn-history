<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services\Upcasting\Upcasters;

use App\Application\Shared\Contracts\UpcasterInterface;

/**
 * UserRegisteredV1toV2Upcaster
 *
 * Example transformation:
 * V1: { "fullname": "John Doe" }
 * V2: { "first_name": "John", "last_name": "Doe" }
 */
final class UserRegisteredV1toV2Upcaster implements UpcasterInterface
{
    public function supports(string $eventType, string $sourceVersion): bool
    {
        return $eventType === 'App\Domain\Identity\Events\UserRegistered' && $sourceVersion === '1.0';
    }

    public function getTargetVersion(): string
    {
        return '2.0';
    }

    public function upcast(array $payload): array
    {
        // Logic: Rename 'fullname' to 'name'
        if (isset($payload['fullname'])) {
            $payload['name'] = $payload['fullname'];
            unset($payload['fullname']);
        }

        return $payload;
    }
}
