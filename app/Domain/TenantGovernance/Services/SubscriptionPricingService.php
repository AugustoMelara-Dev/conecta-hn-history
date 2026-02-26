<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Settings\GeneralSettings;

final readonly class SubscriptionPricingService
{
    public function __construct(
        private GeneralSettings $settings,
    ) {}

    public function getProductLimit(string $plan): int
    {
        return $plan === 'pro'
            ? ($this->settings->pro_plan_product_limit ?? 100)
            : ($this->settings->free_plan_product_limit ?? 10);
    }

    public function getPlanPrice(string $plan): float
    {
        return $plan === 'pro'
            ? ($this->settings->pro_plan_price ?? 500.00)
            : 0.00;
    }

    public function getPlanType(string $plan): \App\Domain\TenantGovernance\Enums\TenantPlan
    {
        return $plan === 'pro' 
            ? \App\Domain\TenantGovernance\Enums\TenantPlan::PRO 
            : \App\Domain\TenantGovernance\Enums\TenantPlan::SEMILLA;
    }
}
