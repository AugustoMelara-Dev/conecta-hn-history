<?php

declare(strict_types=1);

namespace App\Application\Billing\Commands;

use App\Domain\Shared\Contracts\IdempotentCommandInterface;

/**
 * ReportPaymentCommand - Immutable DTO
 *
 * Enforcing strict Idempotency Key requirement for Level 11 Resilience.
 */
final readonly class ReportPaymentCommand implements IdempotentCommandInterface
{
    public function __construct(
        public string $tenantId,
        public string $paymentReference,
        public int $amountInCents,
        public string $currency,
        public string $bank,
        // STRICT REQUIREMENT: Idempotency Key is now mandatory.
        public string $idempotencyKey,
        public ?string $proofOfPaymentPath = null,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
