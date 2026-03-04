<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Contracts\SearchRepository;
use App\Models\Tenant;

final class EloquentSearchRepository implements SearchRepository
{
    public function searchTenants(string $query, ?string $citySlug = null, ?string $categorySlug = null, int $limit = 20): array
    {
        return Tenant::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->when($citySlug, function ($q, $slug) {
                $q->whereHas('city', fn ($sq) => $sq->where('slug', $slug));
            })
            ->when($categorySlug, function ($q, $slug) {
                $q->whereHas('category', fn ($sq) => $sq->where('slug', $slug));
            })
            ->take($limit)
            ->get(['id', 'name', 'slug', 'logo_path', 'category_id', 'city_id'])
            ->toArray();
    }
}
