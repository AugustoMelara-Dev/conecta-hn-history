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
        /** @var array<string, mixed> $configuredPlan */
        $configuredPlan = config('subscriptions.plans.'.$plan->value, []);
        if ($configuredPlan === [] && $plan === TenantPlan::SEMILLA) {
            $configuredPlan = config('subscriptions.plans.free', []);
        }

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
}
