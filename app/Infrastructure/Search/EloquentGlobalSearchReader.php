<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Search\Contracts\GlobalSearchReader;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

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
            ->published()
            ->where('name', 'like', "%{$query}%")
            ->when($citySlug, function (Builder $q) use ($citySlug): void {
                $q->whereHas('city', fn ($sq) => $sq->where('slug', $citySlug));
            })
            ->when($categorySlug, function (Builder $q) use ($categorySlug): void {
                $q->whereHas('category', fn ($sq) => $sq->where('slug', $categorySlug));
            })
            ->with(['city', 'category'])
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
