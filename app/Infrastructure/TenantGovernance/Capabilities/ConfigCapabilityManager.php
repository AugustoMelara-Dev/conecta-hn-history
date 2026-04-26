<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Capabilities;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;

final class ConfigCapabilityManager implements CapabilityManagerInterface
{
    public function __construct(
        private readonly RubroManifestReaderInterface $rubroManifestReader,
    ) {}

    public function has(Tenant $tenant, Capability $capability): bool
    {
        return in_array($capability, $this->allForTenant($tenant), true);
    }

    public function hasForTenantId(string $tenantId, Capability $capability): bool
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $this->has($tenant, $capability);
    }

    /**
     * @return array<int, Capability>
     */
    public function allForTenant(Tenant $tenant): array
    {
        $manifestCapabilities = $this->normalize(
            $this->rubroManifestReader->capabilitiesForTenant($tenant)
        );

        $merged = [];
        foreach ($manifestCapabilities as $capability) {
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
