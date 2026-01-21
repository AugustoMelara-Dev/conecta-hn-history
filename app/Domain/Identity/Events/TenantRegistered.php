<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Contracts\Events\DomainEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\TenantId;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * TenantRegistered - Domain Event
 *
 * Fired when a new Tenant and their Owner (User) are successfully persisted.
 * This is a pure domain fact, decoupled from Framework Auth.
 */
final readonly class TenantRegistered implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    public function __construct(
        public Tenant $tenant,
        public User $user,
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
        return TenantId::fromInt($this->tenant->id);
    }

    public function aggregateId(): int
    {
        return $this->tenant->id;
    }

    public function toPrimitives(): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // Hydration logic would go here if needed for queue reconstruction
        // For this synchronous/internal event, we might not strictly need full hydration implementation
        // if used only in-process, but for correctness:

        $tenant = Tenant::find($primitives['tenant_id']);
        $user = User::find($primitives['user_id']); // Assuming User exists

        if (! $tenant || ! $user) {
            throw new \RuntimeException('Failed to rehydrate TenantRegistered event: Resources missing.');
        }

        return new self(
            tenant: $tenant,
            user: $user,
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null
        );
    }
}
