<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * SubscriptionUpgraded - Domain Event for Plan Upgrade
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TYPE SAFE: Uses string IDs (UUID/ULID compatible).                      ║
 * ║  TRANSACTIONAL: Implements ShouldDispatchAfterCommit for Outbox safety.  ║
 * ║  TEMPORAL ACCURACY: occurredAt is immutable, set at creation time.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SubscriptionUpgraded implements ShouldDispatchAfterCommit
{
    public const SCHEMA_VERSION = 3;

    public function __construct(
        public string $tenantId,
        public string $oldPlanId,
        public string $newPlanId,
        public string $newPlanName,
        public DateTimeImmutable $occurredAt,
        public ?string $transactionId = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {}

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

    /**
     * Reconstruct event from safe payload.
     */
    public static function fromPayload(array $payload): self
    {
        $occurredAt = isset($payload['occurred_at'])
            ? new DateTimeImmutable($payload['occurred_at'])
            : new DateTimeImmutable;

        return new self(
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            oldPlanId: (string) ($payload['old_plan_id'] ?? ''),
            newPlanId: (string) ($payload['new_plan_id'] ?? ''),
            newPlanName: (string) ($payload['new_plan_name'] ?? ''),
            occurredAt: $occurredAt,
            transactionId: isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            schemaVersion: (int) ($payload['schema_version'] ?? self::SCHEMA_VERSION),
        );
    }

    /**
     * Serialize to safe payload for Outbox storage.
     *
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
        ];
    }
}
