<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\AiCreditEntryType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiCreditLedger
{
    /**
     * Appends an immutable lifecycle fact. Duplicate delivery is harmless.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function append(
        string $tenantId,
        AiCreditEntryType $type,
        float $balanceDelta,
        float $reservedDelta,
        string $idempotencyKey,
        ?string $reservationId = null,
        ?string $billingPeriod = null,
        array $metadata = [],
    ): bool {
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 160) {
            throw new \InvalidArgumentException('AI credit ledger idempotency key is invalid.');
        }

        foreach ([$balanceDelta, $reservedDelta] as $delta) {
            if (! is_finite($delta)) {
                throw new \InvalidArgumentException('AI credit ledger delta must be finite.');
            }
        }

        return DB::table('ai_credit_ledger_entries')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'entry_type' => $type->value,
            'balance_delta' => $balanceDelta,
            'reserved_delta' => $reservedDelta,
            'reservation_id' => $reservationId,
            'billing_period' => $billingPeriod,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
