<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

/**
 * SubscriptionSwapResult - Immutable Result of Plan Change Operation
 *
 * Represents the outcome of a subscription plan swap from the payment gateway.
 */
final readonly class SubscriptionSwapResult
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        /** Whether the swap was successful */
        public bool $success,

        /** New subscription period end date */
        public ?\DateTimeImmutable $newPeriodEnd = null,

        /** Transaction ID from gateway */
        public ?string $transactionId = null,

        /** Failure reason if not successful */
        public ?string $failureReason = null,

        /** Canonical provider outcome. */
        public string $status = self::STATUS_COMPLETED,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(
        \DateTimeImmutable $newPeriodEnd,
        string $transactionId
    ): self {
        return new self(
            success: true,
            newPeriodEnd: $newPeriodEnd,
            transactionId: $transactionId,
            status: self::STATUS_COMPLETED,
        );
    }

    public static function pending(string $transactionId): self
    {
        return new self(
            success: false,
            transactionId: $transactionId,
            status: self::STATUS_PENDING,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failed(string $reason): self
    {
        return new self(
            success: false,
            failureReason: $reason,
            status: self::STATUS_FAILED,
        );
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
