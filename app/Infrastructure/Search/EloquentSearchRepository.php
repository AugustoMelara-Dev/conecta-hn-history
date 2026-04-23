<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Contracts\SearchRepository;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

final class EloquentSearchRepository implements SearchRepository
{
    public function searchTenants(string $query, ?string $citySlug = null, ?string $categorySlug = null, int $limit = 20): array
    {
        return Tenant::query()
            ->published()
            ->where(function (Builder $q) use ($query): void {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->when($citySlug, function (Builder $q, string $slug): void {
                $q->whereHas('city', fn ($sq) => $sq->where('slug', $slug));
            })
            ->when($categorySlug, function (Builder $q, string $slug): void {
                $q->whereHas('category', fn ($sq) => $sq->where('slug', $slug));
            })
            ->take($limit)
            ->get(['id', 'name', 'slug', 'logo_path', 'category_id', 'city_id'])
            ->toArray();
    }
}
