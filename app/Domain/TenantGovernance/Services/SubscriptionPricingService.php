<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Contracts\PlanLimitsInterface;

final readonly class SubscriptionPricingService
{
    public function __construct(
        private PlanLimitsInterface $planLimits,
    ) {}

    public function getProductLimit(string $plan): int
    {
        return $this->planLimits->getProductLimit($plan);
    }

    public function getPlanPrice(string $plan): float
    {
        return $this->planLimits->getPlanPrice($plan);
    }

    public function getPlanType(string $plan): \App\Domain\TenantGovernance\Enums\TenantPlan
    {
        return $plan === 'pro'
            ? \App\Domain\TenantGovernance\Enums\TenantPlan::PRO
            : \App\Domain\TenantGovernance\Enums\TenantPlan::SEMILLA;
    }
}
