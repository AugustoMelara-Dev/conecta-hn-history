<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Capabilities;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;

final class ConfigCapabilityManager implements CapabilityManagerInterface
{
    public function has(Tenant $tenant, Capability $capability): bool
    {
        return in_array($capability, $this->allForTenant($tenant), true);
    }

    /**
     * @return array<int, Capability>
     */
    public function allForTenant(Tenant $tenant): array
    {
        $always = $this->normalize(config('capabilities.always', []));
        $businessType = $tenant->business_type;

        if (! $businessType instanceof BusinessType) {
            return $always;
        }

        $typeSpecific = $this->normalize(
            config("capabilities.business_types.{$businessType->value}", [])
        );

        $merged = [];
        foreach (array_merge($always, $typeSpecific) as $capability) {
            $merged[$capability->value] = $capability;
        }

        return array_values($merged);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, Capability>
     */
    private function normalize(array $values): array
    {
        return array_map(
            static fn (string $value): Capability => Capability::from($value),
            $values
        );
    }
}
