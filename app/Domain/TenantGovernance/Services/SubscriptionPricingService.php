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
            ? self::canonicalProPriceInMajorUnits()
            : 0.00;
    }

    public static function canonicalProPriceInMinorUnits(): int
    {
        $price = config('subscriptions.plans.pro.price');

        if (! is_int($price) || $price <= 0) {
            throw new \LogicException('subscriptions.plans.pro.price must be a positive integer in minor currency units.');
        }

        return $price;
    }

    public static function canonicalProPriceInMajorUnits(): float
    {
        return self::canonicalProPriceInMinorUnits() / 100;
    }

    public function getPlanType(string $plan): string
    {
        return $plan === 'pro' ? 'pro' : 'semilla';
    }
}
