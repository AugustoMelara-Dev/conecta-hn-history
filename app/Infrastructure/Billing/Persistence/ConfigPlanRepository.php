<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Domain\Billing\Entities\PlanEntity;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use Illuminate\Support\Str;

/**
 * ConfigPlanRepository - Immutable Plan Configuration
 *
 * Sources plans directly from the Domain Enum/Config.
 */
final class ConfigPlanRepository implements PlanRepositoryInterface
{
    public function findByIdentifier(string $planIdentifier): ?PlanEntity
    {
        $normalized = $planIdentifier === 'free' ? TenantPlan::SEMILLA->value : $planIdentifier;
        $planEnum = TenantPlan::tryFrom($normalized);

        if (! $planEnum) {
            return null;
        }

        return $this->isActive($planEnum) ? $this->mapEnumToEntity($planEnum) : null;
    }

    public function getAllActive(): array
    {
        return array_map(
            fn (TenantPlan $plan) => $this->mapEnumToEntity($plan),
            array_values(array_filter(TenantPlan::cases(), $this->isActive(...)))
        );
    }

    private function mapEnumToEntity(TenantPlan $plan): PlanEntity
    {
        $configuredPlan = $this->configuredPlan($plan);

        $amount = (int) ($configuredPlan['price'] ?? 0);
        $currency = strtoupper((string) ($configuredPlan['currency'] ?? 'HNL'));
        $externalGatewayId = (string) ($configuredPlan['stripe_price_id'] ?? '');

        if ($externalGatewayId === '' && $plan === TenantPlan::PRO) {
            $externalGatewayId = (string) config('services.stripe.price_pro', '');
        }

        return new PlanEntity(
            id: $plan->value,
            name: (string) ($configuredPlan['name'] ?? Str::headline($plan->value)),
            price: new Money(
                amount: $amount,
                currency: $currency
            ),
            externalGatewayId: $externalGatewayId !== '' ? $externalGatewayId : 'plan_'.$plan->value
        );
    }

    /** @return array<string, mixed> */
    private function configuredPlan(TenantPlan $plan): array
    {
        /** @var array<string, mixed> $configuredPlan */
        $configuredPlan = config('subscriptions.plans.'.$plan->value, []);
        if ($configuredPlan === [] && $plan === TenantPlan::SEMILLA) {
            /** @var array<string, mixed> $configuredPlan */
            $configuredPlan = config('subscriptions.plans.free', []);
        }

        return $configuredPlan;
    }

    private function isActive(TenantPlan $plan): bool
    {
        return ($this->configuredPlan($plan)['is_active'] ?? false) === true;
    }
}
