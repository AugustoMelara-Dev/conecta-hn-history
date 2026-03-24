<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * SubscriptionUpgraded - Domain event for plan upgrades.
 *
 * Uses string identifiers and immutable timestamps, and dispatches only after
 * the surrounding transaction commits.
 */
final readonly class SubscriptionUpgraded implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasEventIdentity;

    public const SCHEMA_VERSION = 3;

    public function __construct(
        public string $tenantId,
        public string $oldPlanId,
        public string $newPlanId,
        public string $newPlanName,
        public DateTimeImmutable $occurredAt,
        public ?string $transactionId = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
        ?string $restoredEventId = null,
        ?string $restoredCorrelationId = null,
    ) {
        $identity = self::resolveEventIdentity($restoredEventId, $restoredCorrelationId);
        $this->eventId = $identity['eventId'];
        $this->correlationId = $identity['correlationId'];
    }

    /**
     * Create from upgrade context.
     */
    public static function create(
        string $tenantId,
        string $oldPlanId,
        string $newPlanId,
        string $newPlanName,
        ?string $transactionId = null,
    ): self {
        return new self(
            tenantId: $tenantId,
            oldPlanId: $oldPlanId,
            newPlanId: $newPlanId,
            newPlanName: $newPlanName,
            occurredAt: new DateTimeImmutable,
            transactionId: $transactionId,
        );
    }

    // DomainEvent interface

    public function tenantId(): mixed
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return $this->tenantId;
    }

    public function occurredAt(): DateTimeImmutable
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

    // Serialization

    public static function fromPayload(array $payload): static
    {
        return new self(
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            oldPlanId: (string) ($payload['old_plan_id'] ?? ''),
            newPlanId: (string) ($payload['new_plan_id'] ?? ''),
            newPlanName: (string) ($payload['new_plan_name'] ?? ''),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable,
            transactionId: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            schemaVersion: (int) ($payload['schema_version'] ?? self::SCHEMA_VERSION),
            restoredEventId: $payload['event_id'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'tenant_id' => $this->tenantId,
            'old_plan_id' => $this->oldPlanId,
            'new_plan_id' => $this->newPlanId,
            'new_plan_name' => $this->newPlanName,
            'transaction_id' => $this->transactionId,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
