<?php

declare(strict_types=1);

namespace App\Domain\Booking\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldBeStoredInOutbox;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Traits\HasDomainMetadata;

final readonly class AppointmentCancelled implements DomainEvent, ShouldBeStoredInOutbox
{
    use HasDomainMetadata;

    public const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public int $appointmentId,
        public string $tenantId,
        public int $serviceId,
        public string $scheduledAt,
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
        return TenantId::fromString($this->tenantId);
    }

    public function aggregateId(): string
    {
        return (string) $this->appointmentId;
    }

    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'appointment_id' => $this->appointmentId,
            'tenant_id' => $this->tenantId,
            'service_id' => $this->serviceId,
            'scheduled_at' => $this->scheduledAt,
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'correlation_id' => $this->correlationId,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            appointmentId: (int) $payload['appointment_id'],
            tenantId: (string) $payload['tenant_id'],
            serviceId: (int) $payload['service_id'],
            scheduledAt: (string) $payload['scheduled_at'],
            restoredEventId: $payload['event_id'] ?? null,
            restoredOccurredAt: $payload['occurred_at'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }

    public function getAggregateId(): ?string
    {
        return (string) $this->appointmentId;
    }

    public function getAggregateType(): ?string
    {
        return 'appointment';
    }

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }
}
