<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Persistence;

use App\Domain\Billing\Entities\PlanEntity;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\TenantGovernance\Enums\TenantPlan;

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
        // Pricing Logic (Mocked for Level 30 bootstrapping, should come from Config)
        $prices = [
            TenantPlan::SEMILLA->value => 0,
            TenantPlan::PRO->value => 2900, // 29.00 HNL (Example)
        ];

        return new PlanEntity(
            id: $plan->value,
            name: ucfirst($plan->value),
            price: new Money(
                amount: $prices[$plan->value] ?? 9900,
                currency: 'HNL'
            ),
            externalGatewayId: 'plan_'.$plan->value
        );
    }
}
