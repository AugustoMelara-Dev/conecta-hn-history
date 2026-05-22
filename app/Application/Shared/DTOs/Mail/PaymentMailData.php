<?php

declare(strict_types=1);

namespace App\Application\Shared\DTOs\Mail;

/**
 * PaymentMailData - Immutable DTO for Payment Email Context
 *
 * Captures payment data at dispatch time to avoid SerializesModels.
 */
final readonly class PaymentMailData
{
    public function __construct(
        public int $id,
        public string $amount,
        public string $status,
        public string $reference,
        public string $createdAt,
    ) {}

    /**
     * Create from Payment model (snapshot at dispatch time).
     */
    public static function fromPayment(object $payment): self
    {
        return new self(
            id: (int) $payment->id,
            amount: number_format((float) $payment->amount, 2),
            status: (string) $payment->status,
            reference: (string) ($payment->reference ?? ''),
            createdAt: $payment->created_at?->toDateTimeString() ?? '',
        );
    }
}
