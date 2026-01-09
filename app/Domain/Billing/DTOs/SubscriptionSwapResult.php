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
    public function __construct(
        /** Whether the swap was successful */
        public bool $success,
        
        /** New subscription period end date */
        public ?\DateTimeImmutable $newPeriodEnd = null,
        
        /** Transaction ID from gateway */
        public ?string $transactionId = null,
        
        /** Failure reason if not successful */
        public ?string $failureReason = null,
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
        );
    }
}
