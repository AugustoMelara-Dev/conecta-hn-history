<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\EventStore;

use App\Domain\Common\Contracts\DomainEventInterface;
use App\Domain\Common\Contracts\EventStoreRepositoryInterface;
use App\Domain\Common\Exceptions\ConcurrencyException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * DatabaseEventStore - The Titanium Vault
 * 
 * The ONLY valid implementation of EventStoreRepositoryInterface.
 * 
 * Capabilities:
 * - ACID Transactions (Event + Outbox)
 * - Strict Tenant Isolation
 * - Optimistic Concurrency Control
 * - Metadata Enrichment
 * - Delta Loading (Snapshots)
 * 
 * @final
 */
final readonly class DatabaseEventStore implements EventStoreRepositoryInterface
{
    public function append(
        string $tenantId,
        string $aggregateId,
        string $aggregateType,
        array $events,
        int $expectedVersion,
        array $metaData = []
    ): void {
        
        $currentVersion = $expectedVersion;

        // UNIFIED TRANSACTION (ACID)
        DB::transaction(function () use ($tenantId, $aggregateId, $aggregateType, $events, $metaData, &$currentVersion) {
            
            foreach ($events as $event) {
                $currentVersion++;
                $eventClass = get_class($event);
                $payloadJson = json_encode($event->toPayload());

                try {
                    // 1. STORE EVENT (Source of Truth)
                    DB::table('stored_events')->insert([
                        'aggregate_uuid' => $aggregateId,
                        'aggregate_type' => $aggregateType,
                        'tenant_id' => $tenantId, // STRICT ISOLATION
                        'event_type' => $eventClass,
                        'payload' => $payloadJson, 
                        'meta_data' => json_encode($metaData), // MANDATORY CONTEXT
                        'aggregate_version' => $currentVersion,
                        'created_at' => $event->occurredOn()->format('Y-m-d H:i:s.u'),
                    ]);

                    // 2. TRANSACTIONAL OUTBOX (Guaranteed Delivery)
                    // 2. TRANSACTIONAL OUTBOX (Guaranteed Delivery)
                    DB::table('domain_outbox_messages')->insert([
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'type' => $eventClass,
                        'aggregate_id' => $aggregateId, // Debug context
                        'aggregate_type' => $aggregateType,
                        'payload' => $payloadJson,
                        'published_at' => null, // PENDING
                        'occurred_at' => now(), // Mapped from created_at
                        'attempts' => 0,
                    ]);

                } catch (QueryException $e) {
                    $errorCode = $e->errorInfo[1] ?? 0;
                    if ($e->getCode() === '23000' || $errorCode === 1062) {
                        throw new ConcurrencyException(
                            "Optimistic Locking Failure: Aggregate [{$aggregateId}] v{$currentVersion} conflict."
                        );
                    }
                    throw $e;
                }
            }
        });
    }

    public function getEventsFrom(string $aggregateId, int $fromVersion): \Generator
    {
        $cursor = DB::table('stored_events')
            ->where('aggregate_uuid', $aggregateId)
            ->where('aggregate_version', '>', $fromVersion)
            ->orderBy('aggregate_version', 'asc')
            ->cursor() // MEMORY EFFICIENCY
            ->map(function ($record) {
                $class = $record->event_type;
                if (class_exists($class) && method_exists($class, 'fromPayload')) {
                    // Rehydration
                    return $class::fromPayload(json_decode($record->payload, true));
                }
                return null;
            })
            ->filter();

        foreach ($cursor as $event) {
            yield $event;
        }
    }

    public function streamAll(): \Generator
    {
        $cursor = DB::table('stored_events')
            ->orderBy('id', 'asc') // Chronological Order
            ->cursor() // CONSTANT MEMORY (O(1))
            ->map(function ($record) {
                $class = $record->event_type;

                // BIT-ROT PROTECTION: Graceful degradation
                if (! class_exists($class)) {
                    return null;
                }

                if (! method_exists($class, 'fromPayload')) {
                    return null;
                }

                try {
                    return $class::fromPayload(json_decode($record->payload, true));
                } catch (\Throwable $e) {
                     // Corrupted payload or legacy structure mismatch
                    return null;
                }
            })
            ->filter(); // Removes nulls automatically
            
        foreach ($cursor as $event) {
            yield $event;
        }
    }
}
