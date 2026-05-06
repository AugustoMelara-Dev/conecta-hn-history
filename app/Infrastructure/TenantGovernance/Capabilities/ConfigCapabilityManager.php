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
        $rawPlanType = $tenant->getRawOriginal('plan_type') ?? $tenant->getAttribute('plan_type');
        $plan = $rawPlanType instanceof TenantPlan ? $rawPlanType->value : (string) $rawPlanType;

        if ($plan !== TenantPlan::PRO->value) {
            return [
                Capability::CATALOG_BASIC,
                Capability::CHECKOUT_BASIC,
            ];
        }

        return [
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
    }
}
