<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Domain\Shared\Contracts\IdempotencyLedgerInterface;
use App\Models\Payment;

// use Illuminate\Http\Request; // REMOVED

/**
 * PaymentIdempotencyLedger
 *
 * The single source of truth for Payment Idempotency.
 */
final class PaymentIdempotencyLedger implements IdempotencyLedgerInterface
{
    public function hasProcessed(string $tenantId, string $idempotencyKey): bool
    {
        if (! $tenantId) {
            return false;
        }

        // Check if a payment with this key exists in the DB
        return Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }
}
