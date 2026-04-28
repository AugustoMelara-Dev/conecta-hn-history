<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class SubscriptionCheckoutSessionResult
{
    private function __construct(
        public bool $success,
        public string $status,
        public ?string $sessionId = null,
        public ?string $checkoutUrl = null,
        public ?string $clientSecret = null,
        public ?string $errorMessage = null,
    ) {}

    public static function requiresPayment(string $sessionId, string $checkoutUrl, ?string $clientSecret = null): self
    {
        return new self(
            success: true,
            status: 'requires_payment',
            sessionId: $sessionId,
            checkoutUrl: $checkoutUrl,
            clientSecret: $clientSecret,
        );
    }

    public static function paid(string $sessionId): self
    {
        return new self(
            success: true,
            status: 'paid',
            sessionId: $sessionId,
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
