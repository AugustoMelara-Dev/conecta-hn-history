<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Contracts;

use App\Models\Dynamic\EntityDefinition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface DynamicSearchInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(
        EntityDefinition $entityDefinition,
        string $tenantId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder;
}

