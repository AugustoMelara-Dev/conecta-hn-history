<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

interface CreditLedgerInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function reserve(string $tenantId, float $credits, string $operation, string $idempotencyKey, array $metadata = []): CreditReservation;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function settle(string $tenantId, string $reservationId, float $actualCredits, string $providerRunId, array $metadata = []): CreditSettlement;

    public function refund(string $tenantId, string $reservationId, string $reason, string $idempotencyKey): CreditRefund;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function adjust(string $tenantId, float $credits, string $reason, string $idempotencyKey, array $metadata = []): void;

    public function expire(string $tenantId, string $reservationId, string $idempotencyKey): CreditRefund;
}
