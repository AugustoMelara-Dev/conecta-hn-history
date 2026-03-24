<?php

declare(strict_types=1);

namespace App\Application\Shared\DTOs\Mail;

use App\Domain\TenantGovernance\Enums\TenantPlan;

/**
 * TenantMailData - Immutable DTO for Tenant Email Context
 *
 * Captures tenant data at dispatch time to avoid SerializesModels.
 */
final readonly class TenantMailData
{
    public function __construct(
        public string|int $id,
        public string $name,
        #[\SensitiveParameter] public string $email,
        public string $planType,
        public ?string $subscriptionEndsAt = null,
    ) {}

    /**
     * Create from Tenant model (snapshot at dispatch time).
     */
    public static function fromTenant(object $tenant, ?string $email = null): self
    {
        $planType = $tenant->plan_type ?? 'semilla';

        return new self(
            id: $tenant->id,
            name: (string) $tenant->name,
            email: $email ?? (string) ($tenant->email ?? ''),
            planType: $planType instanceof TenantPlan ? $planType->value : (string) $planType,
            subscriptionEndsAt: $tenant->subscription_ends_at?->toDateTimeString(),
        );
    }
}
