<?php

declare(strict_types=1);

namespace App\Application\Identity;

final readonly class IssuedTenantInvitation
{
    public function __construct(
        public string $invitationId,
        public string $email,
        public string $plainTextToken,
        public \DateTimeInterface $expiresAt,
    ) {}
}
