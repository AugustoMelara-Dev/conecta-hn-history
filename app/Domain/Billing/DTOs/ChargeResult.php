<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

/**
 * ChargeResult - Immutable Result DTO for Payment Operations
 */
final readonly class ChargeResult
{
    public function __construct(
        public bool $success,
        public ?string $transactionId = null,
        public ?string $receiptUrl = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(string $transactionId, ?string $receiptUrl = null): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            receiptUrl: $receiptUrl,
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
        );
    }
}
