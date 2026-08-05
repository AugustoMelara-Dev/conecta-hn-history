<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Products;

use App\Domain\Product\Repositories\ProductRatingMetricsWriter;
use App\Models\Product;

/**
 * Ratings are review-derived data, deliberately outside the operational
 * catalog writer. This port cannot create, edit, publish, delete or stock a
 * product.
 */
final class EloquentProductRatingMetricsWriter implements ProductRatingMetricsWriter
{
    public function updateRatingMetrics(string $tenantId, int $id, float $averageRating, int $totalReviews): bool
    {
        // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        return Product::withoutGlobalScopes()
            ->whereKey($id)
            ->where('tenant_id', $tenantId)
            ->update([
                'rating_avg' => $averageRating,
                'reviews_count' => $totalReviews,
            ]) === 1;
    }
}
