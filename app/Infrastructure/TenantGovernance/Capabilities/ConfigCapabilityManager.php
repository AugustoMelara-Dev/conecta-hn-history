<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Capabilities;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\Billing\Services\PlanEntitlementService;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Models\Tenant;

final class ConfigCapabilityManager implements CapabilityManagerInterface
{
    public function has(object $tenant, Capability $capability): bool
    {
        if (! $tenant instanceof Tenant) {
            return false;
        }

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
    public function allForTenant(object $tenant): array
    {
        if (! $tenant instanceof Tenant) {
            return [];
        }

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
            CommercialMode::HYBRID => [
                Capability::CATALOG_BASIC,
                Capability::CHECKOUT_BASIC,
                Capability::CUSTOMER_DIRECTORY,
                Capability::STAFF_BASIC,
                Capability::APPOINTMENT_BOOKING,
                Capability::STAFF_CALENDAR,
                Capability::APPOINTMENT_REMINDERS,
            ],
            CommercialMode::PRODUCTS => [
                Capability::CATALOG_BASIC,
                Capability::CHECKOUT_BASIC,
            ],
        };

        $plan = (new PlanEntitlementService)->forTenant($tenant);

        return array_values(array_unique(
            array_merge($capabilities, $plan->capabilities),
            SORT_REGULAR,
        ));
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
}
