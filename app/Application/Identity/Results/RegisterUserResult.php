<?php

declare(strict_types=1);

namespace App\Application\Identity\Results;

use DateTimeImmutable;

/**
 * Class RegisterUserResult
 *
 * Immutable DTO representing the outcome of a user registration.
 * strictly ensures type safety and privacy (no passwords).
 */
final readonly class RegisterUserResult
{
    public function __construct(
        public string $id,
        public string $tenantId,
        #[\SensitiveParameter] public string $email,
        public DateTimeImmutable $registeredAt,
        public bool $requiresVerification = true,
    ) {}
}
