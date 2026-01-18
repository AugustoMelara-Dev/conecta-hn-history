<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Readers;

use App\Application\Shared\Contracts\ReferenceDataReaderInterface;
use App\Models\Category;
use App\Models\City;

/**
 * EloquentReferenceDataReader - Infrastructure Adapter for Reference Data
 *
 * ONLY CLASS allowed to import City and Category models.
 */
final class EloquentReferenceDataReader implements ReferenceDataReaderInterface
{
    public function getAvailableCities(): array
    {
        return City::query()
            ->where('is_available', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getActiveCategories(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->toArray();
    }
}
