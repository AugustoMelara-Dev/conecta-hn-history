<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

final readonly class UserPasswordResetRequested
{
    public function __construct(
        public string $userId,
        public string $tenantId
    ) {}
}
