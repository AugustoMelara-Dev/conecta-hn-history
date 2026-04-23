<?php

declare(strict_types=1);

namespace App\Domain\Directory\Contracts;

interface DirectoryGuideReader
{
    /**
     * Search published tenants for directory listing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchPublishedTenants(
        ?string $search,
        ?string $citySlug,
        ?string $categorySlug,
        int $limit = 12
    ): array;

    /**
     * Recommend published and available products by budget.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recommendByBudget(
        float $budget,
        ?string $citySlug,
        ?string $categorySlug,
        ?string $search = null,
        int $limit = 8,
        array $hintProductIds = []
    ): array;

    /**
     * Apply hybrid rerank (SQL candidates + embedding relevance scoring).
     *
     * @param  array<int, array<string, mixed>>  $recommendations
     * @return array<int, array<string, mixed>>
     */
    public function rerankRecommendations(
        array $recommendations,
        ?string $search,
        int $limit = 8
    ): array;
}
