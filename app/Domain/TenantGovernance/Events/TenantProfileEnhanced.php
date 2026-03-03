<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Events;

// Assuming a base abstract class or interface exists, or just standalone
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * TenantProfileEnhanced - Domain Event (Scalar Compliant)
 *
 * Represents the fact that a tenant's profile was enhanced by AI.
 * Updated to "Titanium Standard":
 * - Scalar Constructor strings/ints (No Value Objects).
 * - Implements toPayload/fromPayload.
 * - Outbox Ready.
 */
final readonly class TenantProfileEnhanced implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly string $tenant_id,
        public readonly int $user_id, // IDs as scalar
        public readonly string $occurred_at, // ISO8601 String
        public readonly array $enhanced_fields // Array is scalar-ish enough for JSON
    ) {}

    /*
    public static function fromTenant(
        \App\Models\Tenant $tenant,
        array $enhancedFields
    ): self {
        return new self(
            tenant_id: (string) $tenant->id,
            user_id: (int) $tenant->user_id,
            occurred_at: now()->toIso8601String(),
            enhanced_fields: $enhancedFields
        );
    }
    */

    public static function fromPayload(array $payload): self
    {
        return new self(
            tenant_id: (string) ($payload['tenant_id'] ?? ''),
            user_id: (int) ($payload['user_id'] ?? 0),
            occurred_at: (string) ($payload['occurred_at'] ?? date('c')),
            enhanced_fields: (array) ($payload['enhanced_fields'] ?? [])
        );
    }

    public function toPayload(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'occurred_at' => $this->occurred_at,
            'enhanced_fields' => $this->enhanced_fields,
        ];
    }
}
