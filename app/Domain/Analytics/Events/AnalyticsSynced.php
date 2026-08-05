<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Events;

use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Traits\HasDomainMetadata;
use DateTimeImmutable;

/**
 * AnalyticsSynced - Observability Event
 *
 * Fired when the nightly analytics sync job completes a batch.
 */
final readonly class AnalyticsSynced implements DomainEvent
{
    use HasDomainMetadata;

    public function __construct(
        public TenantId $tenantId,
        public int $processedRecords,
        public DateTimeImmutable $occurredOn,
        // Restoration parameters
        ?string $restoredEventId = null,
        ?string $restoredOccurredAt = null,
        ?string $restoredCorrelationId = null,
    ) {
        $this->initMetadata(
            correlationId: $restoredCorrelationId,
            restoredEventId: $restoredEventId,
            restoredOccurredAt: $restoredOccurredAt,
        );
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
            'tenant_id' => $this->tenantId->toString(),
            'processed_records' => $this->processedRecords,
            'occurred_on' => $this->occurredOn->format(DATE_ATOM),
            'event_id' => $this->eventId,
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            tenantId: TenantId::fromSerialized($primitives['tenant_id']),
            processedRecords: $primitives['processed_records'],
            occurredOn: new DateTimeImmutable($primitives['occurred_on']),
            restoredEventId: $primitives['event_id'] ?? null,
        );
    }
}
