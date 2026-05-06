<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use Illuminate\Support\Collection;

interface SearchRepository
{
    /**
     * Search for tenants filtering by query, city, and category.
     *
     * @param  string  $query  The search term (min 3 chars recommended).
     * @param  string|null  $citySlug  Optional city slug filter.
     * @param  string|null  $categorySlug  Optional category slug filter.
     * @param  int  $limit  Max results to return.
     * @return Collection<int, \App\Domain\TenantGovernance\Models\Tenant>
     */
    public function searchTenants(string $query, ?string $citySlug = null, ?string $categorySlug = null, int $limit = 20): Collection;
}
