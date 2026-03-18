<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * OutboxRepositoryInterface - Transactional Event Storage
 *
 * Port for persisting domain events within the business transaction.
 * Follows the "Transactional Outbox" pattern:
 *  - Events are persisted in the same transaction as business data
 *  - Guarantees atomic consistency (all-or-nothing)
 *  - A separate worker polls the outbox and dispatches events
 */
interface OutboxRepositoryInterface
{
    /**
     * Append a domain event to the outbox for reliable delivery.
     *
     * MUST be called within the same database transaction as the aggregate save.
     * Failure to do so breaks the atomicity guarantee.
     *
     * @param  object  $event  The domain event to persist
     */
    public function append(object $event): void;

    /**
     * Retrieves a batch of stale locked messages for sovereign recovery.
     *
     * @param  int  $batchSize  Maximum number of messages to recover
     * @param  int  $staleThresholdSeconds  Seconds after which a lock is considered stale
     * @param  array<int>  $ownedPartitionKeys  Explicit list of partitions this worker owns
     * @return array<int, object> Ordered list of outbox message objects
     */
    public function retrieveNextStaleBatch(int $batchSize, int $staleThresholdSeconds, array $ownedPartitionKeys): array;
}
