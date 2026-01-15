<?php

declare(strict_types=1);

namespace App\Application\Billing\Results;

use App\Domain\Billing\Entities\PaymentEntity;

/**
 * ReportPaymentResult - Immutable Result DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL COMPLIANT: Uses PaymentEntity (Domain), not Eloquent Model. ║
 * ║  ZERO HTTP LEAKAGE: Pure transport object for Application layer.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ReportPaymentResult
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $bank,
        public string $referenceNumber,
        public int $amountInCents,
        public string $currency,
        public string $status,
        public ?string $receiptPath
    ) {}

    /**
     * Factory method to create DTO from PaymentEntity (Domain).
     * No Eloquent dependencies.
     */
    public static function fromEntity(PaymentEntity $payment): self
    {
        return new self(
            id: $payment->id,
            tenantId: $payment->tenantId,
            bank: $payment->bank,
            referenceNumber: $payment->referenceNumber,
            amountInCents: $payment->amount->amount,
            currency: $payment->amount->currency,
            status: $payment->status->value,
            receiptPath: $payment->proofUrl
        );
    }
}
