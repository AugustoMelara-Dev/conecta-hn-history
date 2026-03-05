<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Search\Contracts\GlobalSearchReader;
use App\Models\Tenant;

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
    public function search(int $tenantId, string $query): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function searchTenants(string $query, ?string $citySlug = null, ?string $categorySlug = null, int $limit = 20): array
    {
        return Tenant::query()
            ->where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->when($citySlug, function ($q) use ($citySlug) {
                $q->whereHas('city', fn ($sq) => $sq->where('slug', $citySlug));
            })
            ->when($categorySlug, function ($q) use ($categorySlug) {
                $q->whereHas('category', fn ($sq) => $sq->where('slug', $categorySlug));
            })
            ->with(['city', 'category'])
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
