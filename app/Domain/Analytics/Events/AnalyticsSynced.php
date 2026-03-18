<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Traits\HasDomainMetadata;
use App\Domain\Shared\ValueObjects\TenantId;
use DateTimeImmutable;

/**
 * AnalyticsSynced - Observability Event
 *
 * Fired when the nightly analytics sync job completes a batch.
 */
final readonly class AnalyticsSynced implements DomainEvent
{
    use HasDomainMetadata;

    private const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public TenantId $tenantId,
        public int $processedRecords,
        public DateTimeImmutable $occurredOn,
        // Restoration parameters
        ?string $restoredEventId = null,
        ?string $restoredOccurredAt = null,
        ?string $restoredCorrelationId = null,
    ) {
        $metadata = self::resolveMetadata(
            correlationId: $restoredCorrelationId,
            restoredEventId: $restoredEventId,
            restoredOccurredAt: $restoredOccurredAt,
        );
        $this->eventId = $metadata['eventId'];
        $this->correlationId = $metadata['correlationId'];
        $this->occurredAt = $metadata['occurredAt'];
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return $this->tenantId->toString();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function toPrimitives(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'tenant_id' => $this->tenantId->toString(),
            'processed_records' => $this->processedRecords,
            'occurred_on' => $this->occurredOn->format(DATE_ATOM),
            'event_id' => $this->eventId,
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            tenantId: TenantId::fromString((string) $primitives['tenant_id']),
            processedRecords: $primitives['processed_records'],
            occurredOn: new DateTimeImmutable($primitives['occurred_on']),
            restoredEventId: $primitives['event_id'] ?? null,
        );
    }
}
