<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

/**
 * EloquentSubscriptionIntentRepository - Infrastructure Implementation
 *
 * Uses query builder (ConnectionInterface) to interact with the
 * subscription_intents table. This is the correct layer for raw DB access.
 */
final readonly class EloquentSubscriptionIntentRepository implements SubscriptionIntentRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function insertOrIgnore(array $attributes): bool
    {
        try {
            $this->connection->table('subscription_intents')->insert($attributes);

            return true;
        } catch (QueryException $e) {
            // Duplicate entry (MySQL code 23000 / 1062)
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    public function findByTenantAndIdempotencyKey(string $tenantId, string $clientIdempotencyKey): ?object
    {
        return $this->connection->table('subscription_intents')
            ->where('tenant_id', $tenantId)
            ->where('client_idempotency_key', $clientIdempotencyKey)
            ->first();
    }

    public function updateStatus(string $intentId, array $attributes): void
    {
        $this->connection->table('subscription_intents')
            ->where('id', $intentId)
            ->update(array_merge($attributes, ['updated_at' => now()]));
    }

    public function findPendingOlderThan(string $tenantId, string $olderThan): iterable
    {
        return $this->connection->table('subscription_intents')
            ->where('tenant_id', $tenantId)
            ->where('status', 'PENDING')
            ->where('created_at', '<', $olderThan)
            ->get();
    }
}
