<?php

declare(strict_types=1);

namespace App\Application\Identity;

final readonly class TenantInvitationPreview
{
    public function __construct(
        public string $tenantId,
        public string $tenantName,
        public string $tenantSlug,
        public string $inviterName,
        public string $email,
        public string $role,
        public \DateTimeInterface $expiresAt,
    ) {}
}
