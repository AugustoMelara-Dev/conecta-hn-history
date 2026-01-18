<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Events;

use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\TenantAwareEvent;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\TenantId;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * TenantUpdated - Event fired when a tenant updates their profile/slug.
 */
final readonly class TenantUpdated implements DomainEvent, TenantAwareEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    public function __construct(
        private TenantId $tenantId,
        public string $oldSlug,
        public string $newSlug,
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
        return $this->tenantId;
    }

    public function aggregateId(): int
    {
        return (int) $this->tenantId->toString();
    }

    public function toPrimitives(): array
    {
        return [
            'tenant_id' => $this->tenantId->toString(),
            'old_slug' => $this->oldSlug,
            'new_slug' => $this->newSlug,
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            tenantId: TenantId::fromInt($primitives['tenant_id']),
            oldSlug: $primitives['old_slug'],
            newSlug: $primitives['new_slug'],
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null
        );
    }


}
