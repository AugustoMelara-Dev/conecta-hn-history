<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantPlan;

final readonly class PlanEntitlementSnapshot
{
    /**
     * @param  array<string, int|float>  $limits
     * @param  list<Capability>  $capabilities
     * @param  list<string>  $features
     */
    public function __construct(
        public TenantPlan $plan,
        public string $name,
        public string $description,
        public int $priceMinor,
        public string $currency,
        public array $limits,
        public array $capabilities,
        public array $features,
    ) {}

    public function limit(string $key): int|float
    {
        return $this->limits[$key] ?? 0;
    }

    public function includes(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->plan->value,
            'name' => $this->name,
            'description' => $this->description,
            'price_minor' => $this->priceMinor,
            'currency' => $this->currency,
            'limits' => $this->limits,
            'features' => $this->features,
            'capabilities' => array_map(
                static fn (Capability $capability): string => $capability->value,
                $this->capabilities,
            ),
        ];
    }
}
