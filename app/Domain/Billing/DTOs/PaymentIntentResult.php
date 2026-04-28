<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class PaymentIntentResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $transactionId = null,
        public ?string $clientSecret = null,
        public ?string $errorMessage = null,
    ) {}

    public static function requiresPayment(string $transactionId, string $clientSecret): self
    {
        return new self(
            success: true,
            status: 'requires_payment',
            transactionId: $transactionId,
            clientSecret: $clientSecret,
        );
    }

    public static function paid(string $transactionId): self
    {
        return new self(
            success: true,
            status: 'paid',
            transactionId: $transactionId,
        );
    }

    public static function failure(string $message): self
    {
        return new self(
            success: false,
            status: 'failed',
            errorMessage: $message,
        );
    }
}
