<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use App\Application\Billing\Contracts\SubscriptionIntentStoreInterface;
use App\Application\Billing\DTOs\SubscriptionIntentRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentSubscriptionIntentStore implements SubscriptionIntentStoreInterface
{
    public function createOrRecoverPending(
        string $intentId,
        string $tenantId,
        string $clientIdempotencyKey,
        string $planIdentifier,
        string $gatewayProvider,
        string $pendingStatus,
    ): SubscriptionIntentRecord {
        try {
            DB::table('subscription_intents')->insert([
                'id' => $intentId,
                'tenant_id' => $tenantId,
                'client_idempotency_key' => $clientIdempotencyKey,
                'plan_identifier' => $planIdentifier,
                'gateway_provider' => $gatewayProvider,
                'status' => $pendingStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return new SubscriptionIntentRecord($intentId, $pendingStatus, false);
        } catch (QueryException $e) {
            if (! $this->isDuplicate($e)) {
                throw $e;
            }
        }

        $existing = DB::table('subscription_intents')
            ->where('tenant_id', $tenantId)
            ->where('client_idempotency_key', $clientIdempotencyKey)
            ->first();

        if (! is_object($existing)) {
            throw new \RuntimeException('Race condition anomaly: Duplicate detected but row not found.');
        }

        return new SubscriptionIntentRecord(
            id: (string) $existing->id,
            status: (string) $existing->status,
            recovered: true,
        );
    }

    public function markFailed(string $intentId, ?string $reason): void
    {
        DB::table('subscription_intents')->where('id', $intentId)->update([
            'status' => 'FAILED',
            'error_reason' => $reason,
            'updated_at' => now(),
        ]);
    }

    public function markCompleted(string $intentId, ?string $gatewayTransactionId): void
    {
        DB::table('subscription_intents')->where('id', $intentId)->update([
            'status' => 'COMPLETED',
            'gateway_transaction_id' => $gatewayTransactionId,
            'updated_at' => now(),
        ]);
    }

    private function isDuplicate(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000'
            || (string) ($e->errorInfo[0] ?? '') === '23000'
            || ($e->errorInfo[1] ?? null) === 1062;
    }
}
