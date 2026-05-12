<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Capabilities;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;

final class ConfigCapabilityManager implements CapabilityManagerInterface
{
    public function has(Tenant $tenant, Capability $capability): bool
    {
        return in_array($capability, $this->allForTenant($tenant), true);
    }

    public function hasForTenantId(string $tenantId, Capability $capability): bool
    {
        $tenant = Tenant::query()->find($tenantId);

        return $tenant instanceof Tenant && $this->has($tenant, $capability);
    }

    /**
     * @return array<int, Capability>
     */
    public function allForTenant(Tenant $tenant): array
    {
        try {
            $rawPlanType = $tenant->getRawOriginal('plan_type') ?? $tenant->getAttribute('plan_type');
        } catch (\Throwable) {
            $rawPlanType = TenantPlan::SEMILLA->value;
        }
        $plan = $rawPlanType instanceof TenantPlan ? $rawPlanType->value : (string) $rawPlanType;

        if ($plan !== TenantPlan::PRO->value) {
            return [
                Capability::CATALOG_BASIC,
                Capability::CHECKOUT_BASIC,
            ];
        }

        $capabilities = [
            Capability::CATALOG_BASIC,
            Capability::CHECKOUT_BASIC,
            Capability::CUSTOMER_DIRECTORY,
            Capability::CRM_PIPELINE_MANAGEMENT,
            Capability::PAYMENTS_BASIC,
            Capability::NOTIFICATIONS_BASIC,
            Capability::ANALYTICS_BASIC,
            Capability::COUPONS_BASIC,
            Capability::STAFF_BASIC,
        ];

        if ($this->privateAiEnabledForTenant($tenant)) {
            $capabilities[] = Capability::AI_CONTENT;
        }

        return $capabilities;
    }

    private function privateAiEnabledForTenant(Tenant $tenant): bool
    {
        if ((bool) config('ai.private.enabled', false) !== true) {
            return false;
        }

        $allowlist = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config('ai.private.tenant_allowlist', []),
        );

        if ($allowlist === []) {
            return false;
        }

        return in_array(strtolower((string) $tenant->getKey()), $allowlist, true)
            || in_array(strtolower((string) $tenant->slug), $allowlist, true);
    }
}
