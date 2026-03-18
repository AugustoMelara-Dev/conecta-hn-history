<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Persistence;

use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Shared\Contracts\SensitiveDataInterface;
use App\Application\Shared\Contracts\TraceContextInterface;
use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * EloquentOutboxRepository - Level 42 True (ACID Enforced)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ACID ENFORCEMENT: append() MUST be called within a transaction          ║
 * ║  CONTRACT-BASED SERIALIZATION: JSON with version and metadata            ║
 * ║  ZERO NATIVE SERIALIZATION: Only json_encode is used                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentOutboxRepository implements OutboxRepositoryInterface
{
    public function __construct(
        private readonly TraceContextInterface $traceContext
    ) {}

    /**
     * Append an event to the Outbox.
     *
     * CRITICAL: This method MUST be called within an active database transaction.
     * If no transaction is active, a MandatoryTransactionException is thrown.
     *
     * @param  object  $event  The domain event to persist
     *
     * @throws RuntimeException If called outside a transaction
     */
    public function append(object $event): void
    {
        // LEVEL 42: ACID ENFORCEMENT
        // The Outbox write MUST be physically coupled with the business transaction.
        // If Payment save fails, the Outbox entry must also be rolled back.
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException(
                'MandatoryTransactionException: append() must be called within an active database transaction. '.
                'Outbox writes must be atomic with the business entity to prevent distributed inconsistency.'
            );
        }

        // 1. Telemetry and Context Extraction
        $traceId = $this->traceContext->getTraceId();
        $eventType = get_class($event);
        // C-OX-03: Use canonical DomainEvent contract method occurredAt(), not occurredOn()
        $occurredAt = method_exists($event, 'occurredAt') ? $event->occurredAt() : now();

        // 2. Extract context from event if available
        // C-OX-05: Prefer method call over property access (handles TenantId VO → string)
        $tenantId = method_exists($event, 'tenantId')
            ? (string) $event->tenantId()
            : (property_exists($event, 'tenantId') ? (string) $event->tenantId : null);
        $userId = property_exists($event, 'userId') ? $event->userId : null;

        // LEVEL 46: AGGREGATE AFFINITY WITH ATOMIC SEQUENCE
        // Extract aggregate ID for sequence grouping
        $aggregateId = $this->extractAggregateId($event);
        $partitionKey = $this->calculatePartitionKey($aggregateId);

        // LEVEL 46: Use AtomicSequenceGenerator for O(1) sequence generation
        // This replaces the slow lockForUpdate + max() pattern
        $sequenceGenerator = app(\App\Infrastructure\Persistence\AtomicSequenceGenerator::class);
        $sequenceNumber = $sequenceGenerator->getNextSequence($aggregateId);

        // 3. CONTRACT-BASED SERIALIZATION
        // We use a versioned envelope to support schema evolution.
        // Even if the PHP class is renamed, the raw JSON remains parseable.
        $payload = [
            'meta' => [
                'version' => '1.0',
                'event_type' => $eventType,
                'trace_id' => $traceId,
                // C-OX-04: Use event's own correlationId() if provided by HasEventIdentity/HasDomainMetadata
                'correlation_id' => method_exists($event, 'correlationId') ? $event->correlationId() : $traceId,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'aggregate_id' => $aggregateId,
                'sequence_number' => $sequenceNumber,
                'serialized_at' => now()->toIso8601String(),
            ],
            'data' => $this->serializeEvent($event),
        ];

        // 4. Persistence (Atomic with Business Entity)
        OutboxMessage::create([
            'id' => (string) Str::ulid(), // ULID for sortable, distributed IDs
            'type' => $eventType,
            'aggregate_id' => $aggregateId,
            'partition_key' => $partitionKey,
            'sequence_number' => $sequenceNumber,
            'payload' => $payload,
            'occurred_at' => $occurredAt,
            'status' => OutboxMessage::STATUS_PENDING,
            'attempts' => 0,
            'recovery_count' => 0,
            'published_at' => null,
            'locked_at' => null,
        ]);
    }

    /**
     * LEVEL 44: Extract aggregate ID from event.
     * Looks for common aggregate ID property names.
     */
    private function extractAggregateId(object $event): ?string
    {
        // C-OX-02: Prefer canonical DomainEvent/ShouldBeStoredInOutbox contract methods.
        // ShouldBeStoredInOutbox::getAggregateId() takes priority (explicit contract).
        if (method_exists($event, 'getAggregateId')) {
            $value = $event->getAggregateId();

            return $value !== null ? (string) $value : null;
        }

        // DomainEvent::aggregateId() is the canonical fallback.
        if (method_exists($event, 'aggregateId')) {
            $value = $event->aggregateId();

            return $value !== null ? (string) $value : null;
        }

        // Fallback heuristic for legacy events without DomainEvent contract.
        $candidates = ['paymentId', 'orderId', 'invoiceId', 'userId', 'tenantId', 'id'];

        foreach ($candidates as $candidate) {
            if (property_exists($event, $candidate)) {
                $value = $event->$candidate;

                return $value !== null ? (string) $value : null;
            }
        }

        return null;
    }

    /**
     * LEVEL 44: Calculate deterministic partition key.
     * Uses crc32 for fast, deterministic hashing.
     */
    private function calculatePartitionKey(?string $aggregateId): int
    {
        if ($aggregateId === null) {
            return 0; // Default partition for events without aggregate
        }

        return abs(crc32($aggregateId)) % OutboxMessage::PARTITION_COUNT;
    }

    /**
     * LEVEL 45: Get next sequence number for aggregate (ATOMIC).
     *
     * Uses SELECT FOR UPDATE to lock aggregate rows during sequence calculation.
     * This prevents race conditions where two concurrent inserts get the same sequence.
     *
     * PREREQUISITE: This method MUST be called within an active transaction
     * (which is enforced by the transaction check at the start of append()).
     */
    private function getNextSequenceNumber(?string $aggregateId): int
    {
        if ($aggregateId === null) {
            return 0;
        }

        // LEVEL 45: ATOMIC SEQUENCE GENERATION
        // lockForUpdate() acquires an exclusive lock on rows matching the aggregate_id.
        // This ensures that concurrent inserts for the same aggregate serialize,
        // preventing duplicate sequence numbers.
        $lastSequence = OutboxMessage::query()
            ->where('aggregate_id', $aggregateId)
            ->lockForUpdate() // Serialize concurrent access to same aggregate
            ->max('sequence_number');

        return ($lastSequence ?? 0) + 1;
    }

    /**
     * LEVEL 70: RECOVER STALE LOCKS WITH SEQUENCE BARRIER & PARTITION PUSHDOWN
     *
     * Retrieves stale messages (locked > threshold) strictly within owned partitions.
     * Enforces Head-of-Line Barrier: Only picks the lowest sequence_number for any aggregate.
     *
     * @param  int  $batchSize  Maximum number of messages to recover
     * @param  int  $staleThresholdSeconds  Seconds after which a lock is considered stale
     * @param  array<int>  $ownedPartitionKeys  Explicit list of partitions this worker owns
     * @return \Illuminate\Support\Collection<OutboxMessage>
     */
    public function retrieveNextStaleBatch(int $batchSize, int $staleThresholdSeconds, array $ownedPartitionKeys): array
    {
        $thresholdTime = now()->subSeconds($staleThresholdSeconds);

        // LEVEL 53: SEQUENCE BARRIER RECOVERY
        // This query implements the "Head-of-Line" Logic.
        // We only want to recover a stale message IF it is the "next" expected message
        // for that aggregate. We must not recover Message #5 if Message #4 is still PENDING.
        //
        // Logic:
        // 1. Find candidates that are PROCESSING and STALE (locked_at < threshold)
        // 2. INNER JOIN against the aggregate's MIN(sequence_number) of ALL incomplete messages
        // 3. This ensures we only pick the stale message if it IS the minimum sequence.

        // LEVEL 55: DYNAMIC QUERY STRATEGIST (WATCHDOG)
        $strategist = app(\App\Infrastructure\Persistence\QueryStrategist::class);
        $strategyHint = $strategist->getOptimizationHint('retrieveNextStaleBatch');

        // Start Watchdog Timer
        $startTime = microtime(true);

        $results = OutboxMessage::query()
            ->from(DB::raw("outbox_messages {$strategyHint}"))
            ->select('outbox_messages.*')
            ->joinSub(
                // Subquery also needs the hint to be consistent
                OutboxMessage::query()
                    ->from(DB::raw("outbox_messages {$strategyHint}"))
                    ->select('aggregate_id', DB::raw('MIN(sequence_number) as min_seq'))
                    ->whereIn('status', [OutboxMessage::STATUS_PENDING, OutboxMessage::STATUS_PROCESSING])
                    // LEVEL 76: THE PARTITION DAM (Recursive Predicate Pushdown)
                    // We MUST filter by partition inside the subquery to prevent Full Index/Table Scans.
                    // This creates a "Covering Index" operation on (status, partition_key, aggregate_id, sequence_number).
                    ->whereIn('partition_key', $ownedPartitionKeys)
                    ->groupBy('aggregate_id'),
                'heads',
                function ($join) {
                    $join->on('outbox_messages.aggregate_id', '=', 'heads.aggregate_id')
                        ->on('outbox_messages.sequence_number', '=', 'heads.min_seq');
                }
            )
            ->where('outbox_messages.status', OutboxMessage::STATUS_PROCESSING)
            ->where('outbox_messages.locked_at', '<', $thresholdTime)
            ->whereIn('outbox_messages.partition_key', $ownedPartitionKeys) // LEVEL 70: SQL PREDICATE PUSHDOWN
            ->limit($batchSize)
            ->lockForUpdate() // Lock them to prevent race conditions during recovery claim
            ->get();

        // Stop Watchdog Timer & Report
        $durationMs = (microtime(true) - $startTime) * 1000;
        $strategist->recordExecutionTime('retrieveNextStaleBatch', $durationMs);

        return $results->all();

    }

    /**
     * Serialize event to array using only public properties.
     * NO native PHP serialize() - only JSON-compatible structures.
     */
    private function serializeEvent(object $event): array
    {
        // C-OX-01: Prefer canonical toPayload() contract (versioned, schema-safe).
        // This respects the DomainEvent/ShouldBeStoredInOutbox contract built in Domain Layer audit.
        // Reflection fallback retained for legacy events without toPayload().
        if (method_exists($event, 'toPayload')) {
            $data = $event->toPayload();
        } else {
            $reflection = new \ReflectionClass($event);
            $data = [];

            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $value = $property->getValue($event);

                // Handle DateTimeInterface specially
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format(\DateTimeInterface::ATOM);
                }

                // Handle nested objects (Value Objects)
                if (is_object($value) && ! ($value instanceof \DateTimeInterface)) {
                    $value = (array) $value;
                }

                $data[$property->getName()] = $value;
            }
        }

        // REDACTION: If implementing SensitiveDataInterface, scrub secrets
        if ($event instanceof SensitiveDataInterface) {
            foreach ($event->getSensitiveProperties() as $sensitiveKey) {
                if (array_key_exists($sensitiveKey, $data)) {
                    $data[$sensitiveKey] = '[REDACTED]';
                }
            }
        }

        return $data;
    }
}
