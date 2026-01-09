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
     *
     * @param int $limit
     * @return Collection
     */
    public function getFeaturedTenants(int $limit = 8): Collection;

    /**
     * Search tenants with pagination.
     *
     * @param DirectorySearchData $filters
     * @return LengthAwarePaginator
     */
    public function searchTenants(DirectorySearchData $filters): LengthAwarePaginator;

    /**
     * Get all cities that given tenants belong to.
     *
     * @return Collection
     */
    public function getCitiesWithTenants(): Collection;

    /**
     * Get all categories with active tenants.
     *
     * @return Collection
     */
    public function getCategoriesWithTenants(): Collection;

    /**
     * Find a city by its slug.
     *
     * @param string $slug
     * @return object|null City data object or null if not found
     */
    public function findCityBySlug(string $slug): ?object;

    /**
     * Find a category by its slug.
     *
     * @param string $slug
     * @return object|null Category data object or null if not found
     */
    public function findCategoryBySlug(string $slug): ?object;
}
