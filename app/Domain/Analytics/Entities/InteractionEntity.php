<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Entities;

use DateTimeImmutable;

/**
 * InteractionEntity - Pure Domain Entity for Interaction
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: No Eloquent dependencies.                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class InteractionEntity
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $type,
        public ?int $productId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $referrer,
        public ?string $url,
        public array $metadata,
        public DateTimeImmutable $createdAt,
    ) {}
}
