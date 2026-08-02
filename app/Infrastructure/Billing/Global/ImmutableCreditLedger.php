<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Global;

use App\Domain\Billing\Global\CreditLedgerInterface;
use App\Domain\Billing\Global\CreditRefund;
use App\Domain\Billing\Global\CreditReservation;
use App\Domain\Billing\Global\CreditSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImmutableCreditLedger implements CreditLedgerInterface
{
    public function reserve(string $tenantId, float $credits, string $operation, string $idempotencyKey, array $metadata = []): CreditReservation
    {
        $this->assertFinite($credits, positive: true);

        $existing = DB::table('global_billing_credit_reservations')
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first(['id', 'tenant_id', 'authorized_credits', 'status', 'idempotency_key']);

        if ($existing !== null) {
            return new CreditReservation(
                reservationId: (string) $existing->id,
                tenantId: (string) $existing->tenant_id,
                authorizedCredits: (float) $existing->authorized_credits,
                status: (string) $existing->status,
                idempotencyKey: (string) $existing->idempotency_key,
            );
        }

        return DB::transaction(function () use ($tenantId, $credits, $operation, $idempotencyKey, $metadata): CreditReservation {
            $account = $this->lockAccount($tenantId);
            $existing = DB::table('global_billing_credit_reservations')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first(['id', 'tenant_id', 'authorized_credits', 'status', 'idempotency_key']);

            if ($existing !== null) {
                return new CreditReservation(
                    reservationId: (string) $existing->id,
                    tenantId: (string) $existing->tenant_id,
                    authorizedCredits: (float) $existing->authorized_credits,
                    status: (string) $existing->status,
                    idempotencyKey: (string) $existing->idempotency_key,
                );
            }

            $available = (float) $account->available_credits - (float) $account->reserved_credits;

            if ($available + 0.000001 < $credits) {
                throw new \RuntimeException('insufficient credits for reservation');
            }

            $reservationId = (string) Str::uuid();
            $reservedAfter = (float) $account->reserved_credits + $credits;
            DB::table('global_billing_credit_accounts')
                ->where('tenant_id', $tenantId)
                ->update([
                    'reserved_credits' => $reservedAfter,
                    'updated_at' => now()->utc(),
                ]);

            DB::table('global_billing_credit_reservations')->insert([
                'id' => $reservationId,
                'tenant_id' => $tenantId,
                'operation' => mb_substr($operation, 0, 120),
                'authorized_credits' => $credits,
                'actual_credits' => null,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'provider_run_id' => null,
                'failure_reason' => null,
                'settled_at' => null,
                'refunded_at' => null,
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);

            $this->appendEntry(
                tenantId: $tenantId,
                reservationId: $reservationId,
                entryType: 'reservation_created',
                creditDelta: 0.0,
                reservedDelta: $credits,
                balanceAfter: (float) $account->available_credits,
                reservedAfter: $reservedAfter,
                idempotencyKey: "ledger:{$reservationId}:reserve",
                metadata: $metadata,
            );

            return new CreditReservation(
                reservationId: $reservationId,
                tenantId: $tenantId,
                authorizedCredits: $credits,
                status: 'pending',
                idempotencyKey: $idempotencyKey,
            );
        }, 3);
    }

    public function settle(string $tenantId, string $reservationId, float $actualCredits, string $providerRunId, array $metadata = []): CreditSettlement
    {
        $this->assertReservationUuid($reservationId);
        $this->assertFinite($actualCredits, positive: false);

        return DB::transaction(function () use ($tenantId, $reservationId, $actualCredits, $providerRunId, $metadata): CreditSettlement {
            $reservation = DB::table('global_billing_credit_reservations')
                ->where('id', $reservationId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw new \RuntimeException('reservation not found');
            }

            if ((string) $reservation->status === 'settled') {
                $reconciliation = DB::table('global_billing_credit_reconciliations')
                    ->where('tenant_id', $tenantId)
                    ->where('reservation_id', $reservationId)
                    ->first();

                return new CreditSettlement(
                    reservationId: $reservationId,
                    status: 'settled',
                    chargedCredits: (float) $reservation->actual_credits,
                    varianceCredits: (float) ($reconciliation->variance_credits ?? 0.0),
                    reconciliationStatus: (string) ($reconciliation->status ?? 'exact'),
                );
            }

            if ((string) $reservation->status !== 'pending') {
                throw new \RuntimeException('reservation is not pending');
            }

            $authorized = (float) $reservation->authorized_credits;
            $charged = min($authorized, $actualCredits);
            $variance = max(0.0, $actualCredits - $authorized);

            $account = $this->lockAccount($tenantId);
            $availableAfter = (float) $account->available_credits - $charged;
            $reservedAfter = max(0.0, (float) $account->reserved_credits - $authorized);
            $spentAfter = (float) $account->spent_credits + $charged;

            DB::table('global_billing_credit_accounts')
                ->where('tenant_id', $tenantId)
                ->update([
                    'available_credits' => $availableAfter,
                    'reserved_credits' => $reservedAfter,
                    'spent_credits' => $spentAfter,
                    'updated_at' => now()->utc(),
                ]);

            DB::table('global_billing_credit_reservations')
                ->where('id', $reservationId)
                ->update([
                    'actual_credits' => $charged,
                    'status' => 'settled',
                    'provider_run_id' => $providerRunId,
                    'settled_at' => now()->utc(),
                    'updated_at' => now()->utc(),
                ]);

            DB::table('global_billing_credit_reconciliations')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'reservation_id' => $reservationId,
                'provider_run_id' => $providerRunId,
                'authorized_credits' => $authorized,
                'actual_credits' => $actualCredits,
                'variance_credits' => $variance,
                'status' => $variance > 0 ? 'provider_overrun_absorbed' : 'exact',
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);

            $this->appendEntry(
                tenantId: $tenantId,
                reservationId: $reservationId,
                entryType: 'reservation_settled',
                creditDelta: -$charged,
                reservedDelta: -$authorized,
                balanceAfter: $availableAfter,
                reservedAfter: $reservedAfter,
                idempotencyKey: "ledger:{$reservationId}:settle",
                metadata: $metadata,
            );

            $this->recordAlerts($tenantId, $account->allowance_credits, $spentAfter);

            return new CreditSettlement(
                reservationId: $reservationId,
                status: 'settled',
                chargedCredits: $charged,
                varianceCredits: $variance,
                reconciliationStatus: $variance > 0 ? 'provider_overrun_absorbed' : 'exact',
            );
        }, 3);
    }

    public function refund(string $tenantId, string $reservationId, string $reason, string $idempotencyKey): CreditRefund
    {
        $this->assertReservationUuid($reservationId);

        return DB::transaction(function () use ($tenantId, $reservationId, $reason, $idempotencyKey): CreditRefund {
            $reservation = DB::table('global_billing_credit_reservations')
                ->where('id', $reservationId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw new \RuntimeException('reservation not found');
            }

            if ((string) $reservation->status === 'refunded') {
                return new CreditRefund($reservationId, 'refunded');
            }

            if ((string) $reservation->status !== 'pending') {
                throw new \RuntimeException('reservation is not refundable');
            }

            $authorized = (float) $reservation->authorized_credits;
            $account = $this->lockAccount($tenantId);
            $reservedAfter = max(0.0, (float) $account->reserved_credits - $authorized);

            DB::table('global_billing_credit_accounts')
                ->where('tenant_id', $tenantId)
                ->update([
                    'reserved_credits' => $reservedAfter,
                    'updated_at' => now()->utc(),
                ]);

            DB::table('global_billing_credit_reservations')
                ->where('id', $reservationId)
                ->update([
                    'status' => 'refunded',
                    'failure_reason' => $reason,
                    'refunded_at' => now()->utc(),
                    'updated_at' => now()->utc(),
                ]);

            $this->appendEntry(
                tenantId: $tenantId,
                reservationId: $reservationId,
                entryType: 'reservation_refunded',
                creditDelta: 0.0,
                reservedDelta: -$authorized,
                balanceAfter: (float) $account->available_credits,
                reservedAfter: $reservedAfter,
                idempotencyKey: $idempotencyKey,
                metadata: ['reason' => $reason],
            );

            return new CreditRefund($reservationId, 'refunded');
        }, 3);
    }

    public function adjust(string $tenantId, float $credits, string $reason, string $idempotencyKey, array $metadata = []): void
    {
        $this->assertFinite($credits, positive: false);

        DB::transaction(function () use ($tenantId, $credits, $reason, $idempotencyKey, $metadata): void {
            $account = $this->lockAccount($tenantId);
            $existing = DB::table('global_billing_credit_ledger_entries')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                return;
            }

            $availableAfter = (float) $account->available_credits + $credits;
            $allowance = isset($metadata['allowance_credits']) && is_numeric($metadata['allowance_credits'])
                ? (float) $metadata['allowance_credits']
                : (float) $account->allowance_credits;

            DB::table('global_billing_credit_accounts')
                ->where('tenant_id', $tenantId)
                ->update([
                    'available_credits' => $availableAfter,
                    'allowance_credits' => $allowance,
                    'updated_at' => now()->utc(),
                ]);

            $this->appendEntry(
                tenantId: $tenantId,
                reservationId: null,
                entryType: $reason,
                creditDelta: $credits,
                reservedDelta: 0.0,
                balanceAfter: $availableAfter,
                reservedAfter: (float) $account->reserved_credits,
                idempotencyKey: $idempotencyKey,
                metadata: $metadata,
            );
        }, 3);
    }

    public function expire(string $tenantId, string $reservationId, string $idempotencyKey): CreditRefund
    {
        return $this->refund($tenantId, $reservationId, 'expired', $idempotencyKey);
    }

    /**
     * @return object{tenant_id:string,available_credits:float,reserved_credits:float,allowance_credits:float,spent_credits:float}
     */
    private function lockAccount(string $tenantId): object
    {
        DB::table('global_billing_credit_accounts')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'available_credits' => 0.0,
            'reserved_credits' => 0.0,
            'allowance_credits' => 0.0,
            'spent_credits' => 0.0,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        $account = DB::table('global_billing_credit_accounts')
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            throw new \RuntimeException('credit account not found');
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function appendEntry(
        string $tenantId,
        ?string $reservationId,
        string $entryType,
        float $creditDelta,
        float $reservedDelta,
        float $balanceAfter,
        float $reservedAfter,
        string $idempotencyKey,
        array $metadata,
    ): void {
        DB::table('global_billing_credit_ledger_entries')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'reservation_id' => $reservationId,
            'entry_type' => $entryType,
            'credit_delta' => $creditDelta,
            'reserved_delta' => $reservedDelta,
            'balance_after' => $balanceAfter,
            'reserved_after' => $reservedAfter,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => now()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
    }

    private function recordAlerts(string $tenantId, mixed $allowanceCredits, float $spentAfter): void
    {
        $allowance = is_numeric($allowanceCredits) ? (float) $allowanceCredits : 0.0;

        if ($allowance <= 0) {
            return;
        }

        $percentage = (int) floor(($spentAfter / $allowance) * 100);
        $thresholds = array_values(array_filter(
            (array) config('dovra_billing.usage_alert_thresholds', [70, 90, 100]),
            static fn (mixed $threshold): bool => is_numeric($threshold),
        ));

        foreach ($thresholds as $threshold) {
            $thresholdInt = (int) $threshold;
            if ($percentage < $thresholdInt) {
                continue;
            }

            DB::table('global_billing_credit_alerts')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'threshold' => $thresholdInt,
                'triggered_at' => now()->utc(),
                'metadata' => json_encode([
                    'percentage' => min(100, $percentage),
                    'allowance_credits' => $allowance,
                    'spent_credits' => $spentAfter,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);
        }
    }

    private function assertFinite(float $credits, bool $positive): void
    {
        if (! is_finite($credits)) {
            throw new \InvalidArgumentException('credits must be finite');
        }

        if ($positive && $credits <= 0) {
            throw new \InvalidArgumentException('credits must be positive');
        }

        if (! $positive && $credits < 0) {
            throw new \InvalidArgumentException('credits must be non-negative');
        }
    }

    private function assertReservationUuid(string $reservationId): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $reservationId) !== 1) {
            throw new \InvalidArgumentException('reservation id must be a UUID');
        }
    }
}
