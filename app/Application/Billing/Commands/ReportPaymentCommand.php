<?php

declare(strict_types=1);

namespace App\Application\Billing\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;

/**
 * ReportPaymentCommand - Immutable DTO
 *
 * Enforcing strict Idempotency Key requirement for Level 11 Resilience.
 */
final readonly class ReportPaymentCommand implements \App\Application\Shared\Contracts\VersionedCommandInterface, IdempotentCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        public string $tenantId,
        public string $paymentReference,
        public int $amountInCents,
        public string $currency,
        public string $bank,
        // STRICT REQUIREMENT: Idempotency Key is now mandatory.
        public string $idempotencyKey,
        public ?string $proofOfPaymentPath = null,
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function toArray(): array
    {
        return [
            'tenantId' => $this->tenantId,
            'paymentReference' => $this->paymentReference,
            'amountInCents' => $this->amountInCents,
            'currency' => $this->currency,
            'bank' => $this->bank,
            'idempotencyKey' => $this->idempotencyKey,
            'proofOfPaymentPath' => $this->proofOfPaymentPath,
            'schemaVersion' => $this->schemaVersion,
        ];
    }
}
