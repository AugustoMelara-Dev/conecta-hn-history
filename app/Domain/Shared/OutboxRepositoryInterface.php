<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Illuminate\Support\Collection;

/**
 * Interface OutboxRepositoryInterface
 *
 * Defines the contract for persisting domain events within a transaction using the Outbox Pattern.
 * Ensures eventual consistency without direct coupling to message brokers.
 */
interface OutboxRepositoryInterface
{
    /**
     * Appends a domain event to the outbox table.
     *
     * @param  object  $event  The domain event to persist.
     */
    public function append(object $event): void;

    /**
     * LEVEL 70: SOVEREIGN RECOVERY
     * Retrieves a batch of stale locked messages, strictly filtered by owned partitions.
     *
     * @param  int  $batchSize  Maximum number of messages to recover
     * @param  int  $staleThresholdSeconds  Seconds after which a lock is considered stale
     * @param  array<int>  $ownedPartitionKeys  Explicit list of partitions this worker owns
     * @return Collection<mixed>
     */
    public function retrieveNextStaleBatch(int $batchSize, int $staleThresholdSeconds, array $ownedPartitionKeys): Collection;
}
