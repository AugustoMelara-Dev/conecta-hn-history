<?php

declare(strict_types=1);

namespace App\Application\Shared\DTOs\Mail;

/**
 * TenantMailData - Immutable DTO for Tenant Email Context
 *
 * Captures tenant data at dispatch time to avoid SerializesModels.
 */
final readonly class TenantMailData
{
    public function __construct(
        public int $id,
        public string $name,
        #[\SensitiveParameter] public string $email,
        public string $planType,
        public ?string $subscriptionEndsAt = null,
    ) {}

    /**
     * Create from Tenant model (snapshot at dispatch time).
     */
    public static function fromTenant(object $tenant): self
    {
        return new self(
            id: $tenant->id,
            name: $tenant->name,
            email: $tenant->email ?? '',
            planType: $tenant->plan_type ?? 'semilla',
            subscriptionEndsAt: $tenant->subscription_ends_at?->toDateTimeString(),
        );
    }
}
