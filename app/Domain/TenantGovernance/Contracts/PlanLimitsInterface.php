<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Contracts;

/**
 * PlanLimitsInterface - Domain Contract for Plan Configuration
 *
 * Abstracts Spatie GeneralSettings (infrastructure) behind a pure domain port.
 * The Infrastructure layer binds a concrete implementation that reads from GeneralSettings.
 *
 * @see App\Settings\GeneralSettings (infrastructure implementation concern)
 */
interface PlanLimitsInterface
{
    /**
     * Get the maximum product count allowed for a given plan.
     */
    public function getProductLimit(string $plan): int;

    /**
     * Get the price for a given plan in the local currency unit.
     */
    public function getPlanPrice(string $plan): float;
}
