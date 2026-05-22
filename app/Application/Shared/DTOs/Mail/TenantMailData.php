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
        public string $email,
        public string $planType,
        public ?string $subscriptionEndsAt = null,
    ) {}

    /**
     * Create from Tenant model (snapshot at dispatch time).
     */
    public static function fromTenant(object $tenant): self
    {
        return new self(
            id: (int) $tenant->id,
            name: (string) $tenant->name,
            email: (string) ($tenant->email ?? ''),
            planType: (string) ($tenant->plan_type ?? 'semilla'),
            subscriptionEndsAt: $tenant->subscription_ends_at?->toDateTimeString(),
        );
    }
}
