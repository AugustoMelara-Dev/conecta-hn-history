<?php

declare(strict_types=1);

namespace App\Events\Appointment;

use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

final readonly class AppointmentCreated implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;

    private string $eventId;

    private DateTimeImmutable $occurredAt;

    public function __construct(
        mixed $appointment,
        ?string $tenantKey = null,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->appointmentId = $appointment->id ?? $appointment;
        $this->tenantKey = (string) ($tenantKey ?? ($appointment->tenant_id ?? 0));
        $this->eventId = $eventId ?? (string) Str::uuid();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }

    public int|string $appointmentId;

    public string $tenantKey;

    public static function fromAppointment(mixed $appointment): self
    {
        return new self($appointment);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString((string) $this->tenantKey);
    }

    public function aggregateId(): string
    {
        return (string) $this->appointmentId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'appointment_id' => (string) $this->appointmentId,
            'tenant_id' => (string) $this->tenantKey,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            appointment: (string) $primitives['appointment_id'],
            tenantKey: (string) $primitives['tenant_id'],
            eventId: isset($primitives['event_id']) ? (string) $primitives['event_id'] : null,
            occurredAt: isset($primitives['occurred_at']) ? new DateTimeImmutable((string) $primitives['occurred_at']) : null,
        );
    }
}
