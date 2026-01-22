<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Search\Contracts\GlobalSearchReader;
// Assuming strict finding, but Search spans domains.
// Assuming strict finding.
use App\Models\Tenant; // Using Domain Model
use Illuminate\Support\Collection;

/**
 * EloquentGlobalSearchReader - Implementation
 *
 * Orchestrates queries across multiple Eloquent models.
 */
final class EloquentGlobalSearchReader implements GlobalSearchReader
{
    /**
     * {@inheritdoc}
     */
    public function search(int $tenantId, string $query): Collection
    {
        // TODO: Implement internal search if needed. For now focusing on Public Platform Search as per Controller.
        // The Controller uses searchTenants.
        // If this method is not used yet, return empty.
        return collect([]);
    }

    /**
     * {@inheritdoc}
     */
    public function searchTenants(string $query, ?string $citySlug = null, ?string $categorySlug = null, int $limit = 20): Collection
    {
        return Tenant::query()
            ->where('is_active', true) // Only active tenants
            ->where('name', 'like', "%{$query}%") // Simple LIKE for now. FullText if scaled.
            ->when($citySlug, function ($q) use ($citySlug) {
                $q->whereHas('city', fn ($sq) => $sq->where('slug', $citySlug));
            })
            ->when($categorySlug, function ($q) use ($categorySlug) {
                $q->whereHas('category', fn ($sq) => $sq->where('slug', $categorySlug));
            })
            ->with(['city', 'category'])
            ->limit($limit)
            ->get();
    }
}
