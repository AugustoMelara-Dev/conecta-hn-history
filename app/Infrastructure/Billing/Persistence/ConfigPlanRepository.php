<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Domain\Billing\Entities\PlanEntity;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Settings\GeneralSettings;
use Throwable;

/**
 * ConfigPlanRepository - Immutable Plan Configuration
 *
 * Sources plans directly from the Domain Enum/Config.
 */
final class ConfigPlanRepository implements PlanRepositoryInterface
{
    public function findByIdentifier(string $planIdentifier): ?PlanEntity
    {
        $planEnum = TenantPlan::tryFrom($planIdentifier);

        if (! $planEnum) {
            return null;
        }

        return $this->mapEnumToEntity($planEnum);
    }

    public function getAllActive(): array
    {
        return array_map(
            fn (TenantPlan $plan) => $this->mapEnumToEntity($plan),
            TenantPlan::cases()
        );
    }

    private function mapEnumToEntity(TenantPlan $plan): PlanEntity
    {
        return new PlanEntity(
            id: $plan->value,
            name: ucfirst($plan->value),
            price: new Money(
                amount: $this->priceForPlan($plan),
                currency: 'HNL'
            ),
            externalGatewayId: $this->externalGatewayIdForPlan($plan)
        );
    }

    private function priceForPlan(TenantPlan $plan): int
    {
        $settings = $this->settings();

        return match ($plan) {
            TenantPlan::SEMILLA => $this->majorToMinor(
                (float) ($settings?->free_plan_price ?? 0.0)
            ),
            TenantPlan::PRO => $this->majorToMinor(
                (float) ($settings?->pro_plan_price ?? $this->configPriceMajor('pro', 499.0))
            ),
            default => (int) config("subscriptions.plans.{$plan->value}.price", 0),
        };
    }

    private function externalGatewayIdForPlan(TenantPlan $plan): string
    {
        if ((bool) config('services.stripe.use_price_ids', false)) {
            return (string) (config("services.stripe.prices.{$plan->value}") ?: 'plan_'.$plan->value);
        }

        return 'plan_'.$plan->value;
    }

    private function configPriceMajor(string $plan, float $fallback): float
    {
        return ((int) config("subscriptions.plans.{$plan}.price", (int) round($fallback * 100))) / 100;
    }

    private function majorToMinor(float $amount): int
    {
        return max(0, (int) round($amount * 100));
    }

    private function settings(): ?GeneralSettings
    {
        try {
            return new GeneralSettings;
        } catch (Throwable) {
            return null;
        }
    }
}
