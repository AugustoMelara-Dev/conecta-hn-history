<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * SubscriptionUpgraded - Domain Event for Plan Upgrade
 *
 * Fired after successful plan change for downstream processing:
 * - Send confirmation email
 * - Update feature limits
 * - Trigger analytics
 */
final readonly class SubscriptionUpgraded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $tenantId,
        public int $oldPlanId,
        public int $newPlanId,
        public string $newPlanName,
        public \DateTimeImmutable $occurredAt,
        public ?string $transactionId = null,
    ) {}

    /**
     * Create from upgrade context.
     */
    public static function create(
        int $tenantId,
        int $oldPlanId,
        int $newPlanId,
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
            'tenant_id' => $this->tenantId,
            'old_plan_id' => $this->oldPlanId,
            'new_plan_id' => $this->newPlanId,
            'new_plan_name' => $this->newPlanName,
            'transaction_id' => $this->transactionId,
            'occurred_at' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
