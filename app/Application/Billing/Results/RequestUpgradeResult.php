<?php

declare(strict_types=1);

namespace App\Application\Billing\Results;

/**
 * RequestUpgradeResult - Immutable Result DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TYPE SAFE: Uses string IDs to align with Domain (UUID/ULID compatible).║
 * ║  ZERO HTTP LEAKAGE: Pure transport object for Application layer.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RequestUpgradeResult
{
    public function __construct(
        public string $tenantId,
        public string $newPlanId,
        public string $status,
        public ?string $validUntil
    ) {}

    /**
     * Factory method to create DTO from updated Tenant state.
     */
    public static function fromTenantState(string $tenantId, string $newPlanId, \DateTimeImmutable|string|null $endsAt): self
    {
        return new self(
            tenantId: $tenantId,
            newPlanId: $newPlanId,
            status: 'active', // Assumed active after successful gateway swap
            validUntil: $endsAt instanceof \DateTimeInterface ? $endsAt->format('Y-m-d') : $endsAt
        );
    }
}
