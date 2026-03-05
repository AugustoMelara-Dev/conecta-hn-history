<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services\Upcasting\Upcasters;

use App\Domain\Shared\Contracts\UpcasterInterface;

/**
 * UserRegisteredV1toV2Upcaster
 *
 * Example transformation:
 * V1: { "fullname": "John Doe" }
 * V2: { "first_name": "John", "last_name": "Doe" }
 */
final class UserRegisteredV1toV2Upcaster implements UpcasterInterface
{
    /**
     * @param  string  $eventType
     * @param  int  $schemaVersion
     */
    public function supports(string $eventType, int $schemaVersion): bool
    {
        return $eventType === 'App\Domain\Identity\Events\UserRegistered' && $schemaVersion === 1;
    }

    public function fromVersion(): int
    {
        return 1;
    }

    public function toVersion(): int
    {
        return 2;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
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
