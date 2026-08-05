<?php

declare(strict_types=1);

namespace App\Domain\Product\Repositories;

/** Narrow derived-enrichment port; it is not an operational catalog writer. */
interface ProductRatingMetricsWriter
{
    public function updateRatingMetrics(string $tenantId, int $id, float $averageRating, int $totalReviews): bool;
}
