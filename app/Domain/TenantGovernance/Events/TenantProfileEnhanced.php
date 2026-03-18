<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;

/**
 * TenantProfileEnhanced - Domain Event (Scalar Compliant)
 *
 * Represents the fact that a tenant's profile was enhanced by AI.
 * Implements DomainEvent (canonical) via Bridge (W-01).
 * Uses HasEventIdentity (W-03).
 */
final readonly class TenantProfileEnhanced implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasEventIdentity;

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
        ?string $restoredEventId = null,
        ?string $restoredCorrelationId = null,
    ) {
        $this->initEventIdentity($restoredEventId, $restoredCorrelationId);
    }

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function tenantId(): mixed
    {
        return $this->tenant_id;
    }

    public function aggregateId(): string
    {
        return $this->tenant_id;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }

    // ─── Serialization ────────────────────────────────────────────────────────

    public static function fromPayload(array $payload): static
    {
        return new self(
            tenant_id: (string) ($payload['tenant_id'] ?? ''),
            user_id: (string) ($payload['user_id'] ?? ''),
            occurredAt: new \DateTimeImmutable($payload['occurred_at'] ?? 'now'),
            enhanced_fields: (array) ($payload['enhanced_fields'] ?? []),
            restoredEventId: $payload['event_id'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }

    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
            'enhanced_fields' => $this->enhanced_fields,
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
