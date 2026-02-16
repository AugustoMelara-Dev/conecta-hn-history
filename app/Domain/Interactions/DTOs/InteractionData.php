<?php

declare(strict_types=1);

namespace App\Domain\Interactions\DTOs;

final readonly class InteractionData
{
    public function __construct(
        public int $tenantId,
        public string $type,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $sessionId,
        public ?int $productId = null,
        public ?array $metadata = null,
    ) {}
}
