<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * SubscriptionUpgraded - Domain Event for Plan Upgrade
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TYPE SAFE: Uses string IDs (UUID/ULID compatible).                      ║
 * ║  TRANSACTIONAL: Implements ShouldDispatchAfterCommit for Outbox safety.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SubscriptionUpgraded implements ShouldDispatchAfterCommit
{
    public const SCHEMA_VERSION = 2;

    public function __construct(
        public int $tenantId,
        public string $oldPlanId,
        public string $newPlanId,
        public string $newPlanName,
        public \DateTimeImmutable $occurredAt,
        public ?string $transactionId = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {}

    /**
     * Create from upgrade context.
     */
    public static function create(
        int $tenantId,
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
            occurredAt: new \DateTimeImmutable(),
            transactionId: $transactionId,
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
            'occurred_at' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
