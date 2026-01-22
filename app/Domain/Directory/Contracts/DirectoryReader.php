<?php

declare(strict_types=1);

namespace App\Domain\Directory\Contracts;

use App\Domain\Directory\DTOs\DirectorySearchData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DirectoryReader
{
    /**
     * Get featured/priority tenants for the directory.
     */
    public function getFeaturedTenants(int $limit = 8): Collection;

    /**
     * Search tenants with pagination.
     */
    public function searchTenants(DirectorySearchData $filters): LengthAwarePaginator;

    /**
     * Get all cities that given tenants belong to.
     */
    public function getCitiesWithTenants(): Collection;

    /**
     * Get all categories with active tenants.
     */
    public function getCategoriesWithTenants(): Collection;

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
