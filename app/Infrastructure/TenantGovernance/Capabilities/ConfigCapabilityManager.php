<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Capabilities;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\CommercialMode;
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

        $capabilities = match ($this->commercialMode($tenant)) {
            CommercialMode::APPOINTMENTS => [
                Capability::CUSTOMER_DIRECTORY,
                Capability::STAFF_BASIC,
                Capability::APPOINTMENT_BOOKING,
                Capability::STAFF_CALENDAR,
                Capability::APPOINTMENT_REMINDERS,
            ],
            CommercialMode::CONSULTING_LEADS => [
                Capability::CUSTOMER_DIRECTORY,
                Capability::CRM_PIPELINE_MANAGEMENT,
                Capability::NOTIFICATIONS_BASIC,
            ],
            CommercialMode::HYBRID => $this->hybridIsAllowed($tenant) ? [
                Capability::CATALOG_BASIC,
                Capability::CHECKOUT_BASIC,
                Capability::CUSTOMER_DIRECTORY,
                Capability::STAFF_BASIC,
                Capability::APPOINTMENT_BOOKING,
                Capability::STAFF_CALENDAR,
            ] : [
                Capability::CUSTOMER_DIRECTORY,
                Capability::CRM_PIPELINE_MANAGEMENT,
            ],
            CommercialMode::PRODUCTS => [
                Capability::CATALOG_BASIC,
                Capability::CHECKOUT_BASIC,
            ],
        };

        if ($plan !== TenantPlan::PRO->value) {
            return array_values(array_unique($capabilities, SORT_REGULAR));
        }

        return array_values(array_unique(array_merge($capabilities, [
            Capability::CATALOG_BASIC,
            Capability::CHECKOUT_BASIC,
            Capability::CUSTOMER_DIRECTORY,
            Capability::CRM_PIPELINE_MANAGEMENT,
            Capability::PAYMENTS_BASIC,
            Capability::NOTIFICATIONS_BASIC,
            Capability::ANALYTICS_BASIC,
            Capability::COUPONS_BASIC,
            Capability::STAFF_BASIC,
            Capability::AI_CONTENT,
        ]), SORT_REGULAR));
    }

    private function commercialMode(Tenant $tenant): CommercialMode
    {
        try {
            $raw = $tenant->getRawOriginal('commercial_mode') ?? $tenant->getAttribute('commercial_mode');
        } catch (\Throwable) {
            $raw = null;
        }

        try {
            $rawBusinessType = $tenant->getRawOriginal('business_type') ?? $tenant->getAttribute('business_type');
        } catch (\Throwable) {
            $rawBusinessType = null;
        }

        $fallbackType = $rawBusinessType instanceof BusinessType
            ? $rawBusinessType
            : BusinessType::tryFrom((string) $rawBusinessType);

        return CommercialMode::safeFrom(
            is_string($raw) ? $raw : ($raw instanceof CommercialMode ? $raw->value : null),
            $fallbackType
        );
    }

    private function hybridIsAllowed(Tenant $tenant): bool
    {
        $allowed = config('conecta.features.hybrid_commercial_mode_tenants', []);

        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        return in_array((string) $tenant->getKey(), $allowed, true)
            || in_array((string) ($tenant->slug ?? ''), $allowed, true);
    }
}
