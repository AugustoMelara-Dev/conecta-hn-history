<?php

declare(strict_types=1);

namespace App\Domain\Public\Contracts;

/**
 * BuyerRepository - Contract for Buyer Data
 */
interface BuyerRepository
{
    /**
     * Get user favorite products with relations.
     */
    public function getFavorites(int $userId): array;

    /**
     * Get followed tenants.
     */
    public function getFollowedTenants(int $userId): array;
}
