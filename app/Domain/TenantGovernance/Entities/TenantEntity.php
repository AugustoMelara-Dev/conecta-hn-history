<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Entities;

use DateTimeImmutable;

/**
 * TenantEntity - Pure Domain Entity
 */
final readonly class TenantEntity
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $name,
        public string $slug,
        public string $planType,
        public string $subscriptionStatus,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
