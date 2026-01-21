<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Domain\Shared\Contracts\IdempotencyLedgerInterface;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * PaymentIdempotencyLedger
 *
 * The single source of truth for Payment Idempotency.
 */
final class PaymentIdempotencyLedger implements IdempotencyLedgerInterface
{
    public function hasProcessed(Request $request, string $idempotencyKey): bool
    {
        $tenantId = $request->input('tenant_id'); // Assumes validated request or middleware order

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
