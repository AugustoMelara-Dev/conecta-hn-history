<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Contracts\Events\DomainEvent;
// use App\Models\Tenant; // REMOVED
// use App\Models\User; // REMOVED
use App\Domain\Shared\Traits\HasDomainMetadata;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * TenantRegistered - Domain Event
 *
 * Fired when a new Tenant and their Owner (User) are successfully persisted.
 * This is a pure domain fact, decoupled from Framework Auth.
 */
final readonly class TenantRegistered implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    private const SCHEMA_VERSION = 1;
    public function schemaVersion(): int { return self::SCHEMA_VERSION; }

    public function __construct(
        public string $tenantId,
        public string $userId,
        public string $selectedPlan = 'free',
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
        return TenantId::fromString((string) $this->tenantId);
    }

    public function aggregateId(): string
    {
        return $this->tenantId;
    }

    public function toPrimitives(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'selected_plan' => $this->selectedPlan,
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // Hydration logic would go here if needed for queue reconstruction
        // For this synchronous/internal event, we might not strictly need full hydration implementation
        // if used only in-process, but for correctness:

        return new self(
            tenantId: (string) $primitives['tenant_id'],
            userId: (string) $primitives['user_id'],
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null
        );
    }
}
