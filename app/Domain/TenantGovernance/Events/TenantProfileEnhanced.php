<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * TenantProfileEnhanced - Domain Event (Scalar Compliant)
 *
 * Represents the fact that a tenant's profile was enhanced by AI.
 * Updated to "Titanium Standard":
 * - Scalar Constructor strings/ints (No Value Objects).
 * - Implements toPayload/fromPayload.
 * - Outbox Ready.
 * Implements DomainEvent (canonical) via Bridge pattern.
 */
final readonly class TenantProfileEnhanced implements DomainEvent, ShouldDispatchAfterCommit
{
    private const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public readonly string $tenant_id,
        public readonly string $user_id,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly array $enhanced_fields,
        /** W-01: DomainEvent metadata — populated by Outbox relay or Handler */
        public string $eventId = '',
        public string $correlationId = '',
    ) {}

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function eventId(): string        { return $this->eventId; }
    public function correlationId(): string  { return $this->correlationId; }
    public function tenantId(): mixed        { return $this->tenant_id; }
    public function aggregateId(): string    { return $this->tenant_id; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }

    /** Bridge: delegates to toPayload(). */
    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    /** Bridge: delegates to fromPayload(). */
    public static function fromPrimitives(array $primitives): self
    {
        return static::fromPayload($primitives);
    }

    // ─── Serialization ────────────────────────────────────────────────────────

    public static function fromPayload(array $payload): self
    {
        return new self(
            tenant_id:      (string) ($payload['tenant_id']      ?? ''),
            user_id:        (string) ($payload['user_id']        ?? ''),
            occurredAt:     new \DateTimeImmutable($payload['occurred_at'] ?? 'now'),
            enhanced_fields: (array) ($payload['enhanced_fields'] ?? []),
            eventId:        (string) ($payload['event_id']        ?? ''),
            correlationId:  (string) ($payload['correlation_id']  ?? ''),
        );
    }

    public function toPayload(): array
    {
        return [
            'schema_version'  => self::SCHEMA_VERSION,
            'tenant_id'       => $this->tenant_id,
            'user_id'         => $this->user_id,
            'occurred_at'     => $this->occurredAt->format(\DateTimeInterface::ATOM),
            'enhanced_fields' => $this->enhanced_fields,
            'event_id'        => $this->eventId,
            'correlation_id'  => $this->correlationId,
        ];
    }
}
