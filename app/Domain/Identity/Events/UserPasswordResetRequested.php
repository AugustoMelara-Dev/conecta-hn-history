<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use DateTimeImmutable;

final readonly class UserPasswordResetRequested
{
    public function __construct(
        public string $userId,
        public string $tenantId,
        public DateTimeImmutable $occurredAt
    ) {}
}
