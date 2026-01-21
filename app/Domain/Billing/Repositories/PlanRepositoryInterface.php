<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\PlanEntity;

/**
 * PlanRepositoryInterface - Plan Configuration Source
 *
 * Decouples pricing logic from application handlers.
 * Allows plans to be sourced from DB, Config, or Cache.
 */
interface PlanRepositoryInterface
{
    /**
     * Find a plan implementation by its identifier.
     *
     * @param  string  $planIdentifier  (e.g., 'pro', 'enterprise')
     */
    public function findByIdentifier(string $planIdentifier): ?PlanEntity;

    /**
     * Get all available active plans.
     *
     * @return PlanEntity[]
     */
    public function getAllActive(): array;
}
