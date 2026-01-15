<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;

/**
 * PaymentEntity - Pure Domain Entity
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  POPO: Plain Old PHP Object - No Eloquent, No Framework Dependencies.   ║
 * ║  IMMUTABLE: All properties are readonly.                                 ║
 * ║  DOMAIN PURE: Uses Money VO and DateTimeImmutable (no Carbon).          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class PaymentEntity
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public Money $amount,
        public string $bank,
        public string $referenceNumber,
        public PaymentStatus $status,
        public ?string $proofUrl,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * Check if payment is pending verification.
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    /**
     * Check if payment has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    /**
     * Check if payment was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === PaymentStatus::REJECTED;
    }
}
