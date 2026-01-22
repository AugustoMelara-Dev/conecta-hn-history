<?php

declare(strict_types=1);

namespace App\Domain\Common\Contracts;

use App\Domain\Common\Exceptions\ConcurrencyException;

/**
 * EventStoreRepositoryInterface - The Secure Persistence Contract
 */
interface EventStoreRepositoryInterface
{
    /**
     * Append events to the store with full security context.
     *
     * @param  string  $tenantId  The Isolation Context (Who owns data).
     * @param  string  $aggregateId  The Entity ID.
     * @param  string  $aggregateType  The Entity Class/Alias.
     * @param  array<DomainEventInterface>  $events  The facts to record.
     * @param  int  $expectedVersion  Optimistic Concurrency Guard.
     * @param  array  $metaData  Audit Trail (IP, User, RequestId).
     *
     * @throws ConcurrencyException
     */
    public function append(
        string $tenantId,
        string $aggregateId,
        string $aggregateType,
        array $events,
        int $expectedVersion,
        array $metaData = []
    ): void;

    /**
     * Reclaim history from a specific point (Delta Loading).
     */
    public function getEventsFrom(string $aggregateId, int $fromVersion): \Generator;

    /**
     * Stream ALL events from the beginning of time.
     * Used for Replays and Migrations.
     */
    public function streamAll(): \Generator;
}
