<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Features;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Application\TenantGovernance\Contracts\TenantFeatureAccessManagerInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;

final readonly class ConfigTenantFeatureAccessManager implements TenantFeatureAccessManagerInterface
{
    public function __construct(
        private CapabilityManagerInterface $capabilityManager,
    ) {}

    public function has(object $tenant, TenantFeature $feature): bool
    {
        if (! $tenant instanceof Tenant) {
            return false;
        }

        $definition = config("tenant-features.features.{$feature->value}");

        if (! is_array($definition)) {
            return false;
        }

        if (! $this->matchesPlan($tenant, $definition['plans'] ?? [])) {
            return false;
        }

        if (! $this->matchesCapabilities($tenant, $definition['all_capabilities'] ?? [], requireAll: true)) {
            return false;
        }

        if (! $this->matchesCapabilities($tenant, $definition['any_capabilities'] ?? [], requireAll: false)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string>  $plans
     */
    private function matchesPlan(Tenant $tenant, array $plans): bool
    {
        if ($plans === []) {
            return true;
        }

        $currentPlan = $this->resolvePlanKey($tenant);

        return in_array($currentPlan, $plans, true);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function matchesCapabilities(Tenant $tenant, array $values, bool $requireAll): bool
    {
        if ($values === []) {
            return true;
        }

        $capabilities = array_map(
            static fn (string $value): Capability => Capability::from($value),
            $values,
        );

        foreach ($capabilities as $capability) {
            $hasCapability = $this->capabilityManager->has($tenant, $capability);

            if ($requireAll && ! $hasCapability) {
                return false;
            }

            if (! $requireAll && $hasCapability) {
                return true;
            }
        }

        return $requireAll;
    }

    private function resolvePlanKey(Tenant $tenant): string
    {
        try {
            $rawPlanType = $tenant->getRawOriginal('plan_type') ?? $tenant->getAttribute('plan_type');
        } catch (\Throwable) {
            $rawPlanType = Tenant::PLAN_SEMILLA;
        }

        return match (true) {
            $rawPlanType instanceof TenantPlan => $rawPlanType->value,
            is_string($rawPlanType) && $rawPlanType !== '' => $rawPlanType,
            default => Tenant::PLAN_SEMILLA,
        };
    }
}
