<?php

declare(strict_types=1);

namespace App\Domain\Search\Contracts;

use Illuminate\Support\Collection;

/**
 * GlobalSearchReader - Domain Contract for Unified Search
 *
 * Responsibility: Port for searching across multiple domains (Products, Categories, etc).
 */
interface GlobalSearchReader
{
    /**
     * Search across all public entities within a tenant scope.
     */
    public function search(int $tenantId, string $query): Collection;

    /**
     * Search specifically for tenants (Unified Platform Search).
     */
    public function searchTenants(string $query, ?string $citySlug = null, ?string $categorySlug = null, int $limit = 20): Collection;
}
