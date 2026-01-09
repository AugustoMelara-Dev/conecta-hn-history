<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Events;

use App\ValueObjects\TenantId;

/**
 * TenantProfileEnhanced - Domain Event for AI Enhancement Completion
 *
 * Represents the fact that a tenant's profile was enhanced by AI.
 * This event is recorded by the Tenant entity and dispatched by the Repository.
 */
final readonly class TenantProfileEnhanced
{
    public function __construct(
        public TenantId $tenantId,
        public int $userId,
        public \DateTimeImmutable $occurredAt,
        public array $enhancedFields
    ) {}

    public static function fromTenant(
        \App\Domain\TenantGovernance\Models\Tenant $tenant,
        array $enhancedFields
    ): self {
        return new self(
            tenantId: TenantId::fromInt($tenant->id),
            userId: $tenant->user_id,
            occurredAt: new \DateTimeImmutable,
            enhancedFields: $enhancedFields
        );
    }
}
