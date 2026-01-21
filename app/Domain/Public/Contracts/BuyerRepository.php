<?php

declare(strict_types=1);

namespace App\Domain\Public\Contracts;

use Illuminate\Support\Collection;

/**
 * BuyerRepository - Contract for Buyer Data
 */
interface BuyerRepository
{
    /**
     * Get user favorite products with relations.
     */
    public function getFavorites(int $userId): Collection;

    /**
     * Get followed tenants.
     */
    public function getFollowedTenants(int $userId): Collection;
}
