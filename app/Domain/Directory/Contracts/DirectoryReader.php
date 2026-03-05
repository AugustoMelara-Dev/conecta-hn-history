<?php

declare(strict_types=1);

namespace App\Domain\Directory\Contracts;

use App\Domain\Directory\DTOs\DirectorySearchData;
use App\Domain\Shared\Contracts\PaginatedResult;

interface DirectoryReader
{
    /**
     * Get featured/priority tenants for the directory.
     */
    public function getFeaturedTenants(int $limit = 8): array;

    /**
     * Search tenants with pagination.
     */
    public function searchTenants(DirectorySearchData $filters): PaginatedResult;

    /**
     * Get all cities that given tenants belong to.
     */
    public function getCitiesWithTenants(): array;

    /**
     * Get all categories with active tenants.
     */
    public function getCategoriesWithTenants(): array;

    /**
     * Find a city by its slug.
     *
     * @return object|null City data object or null if not found
     */
    public function findCityBySlug(string $slug): ?object;

    /**
     * Find a category by its slug.
     *
     * @return object|null Category data object or null if not found
     */
    public function findCategoryBySlug(string $slug): ?object;
}
