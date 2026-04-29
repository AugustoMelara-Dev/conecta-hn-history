<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Settings;

use App\Domain\TenantGovernance\Contracts\PlanLimitsInterface;
use App\Settings\GeneralSettings;

/**
 * SettingsPlanLimits - Infrastructure Adapter for PlanLimitsInterface
 *
 * Reads plan configuration from Spatie GeneralSettings.
 * Implements the Domain contract so SubscriptionPricingService
 * remains free of framework dependencies.
 */
final readonly class SettingsPlanLimits implements PlanLimitsInterface
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
        return match ($plan) {
            'pro' => $this->settings->pro_plan_price ?? 499.00,
            'semilla', 'free' => $this->settings->free_plan_price ?? 0.00,
            default => 0.00,
        };
    }
}
