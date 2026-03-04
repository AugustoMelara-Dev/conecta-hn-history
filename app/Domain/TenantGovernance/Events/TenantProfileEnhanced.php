<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Events;

// Assuming a base abstract class or interface exists, or just standalone
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

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
        public readonly string $user_id, // IDs as scalar
        public readonly \DateTimeImmutable $occurredAt,
        public readonly array $enhanced_fields // Array is scalar-ish enough for JSON
    ) {}




    public static function fromPayload(array $payload): self
    {
        return new self(
            tenant_id: (string) ($payload['tenant_id'] ?? ''),
            user_id: (string) ($payload['user_id'] ?? ''),
            occurredAt: new \DateTimeImmutable($payload['occurred_at'] ?? 'now'),
            enhanced_fields: (array) ($payload['enhanced_fields'] ?? [])
        );
    }

    public function toPayload(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
            'enhanced_fields' => $this->enhanced_fields,
        ];
    }
}
