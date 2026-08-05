<?php

declare(strict_types=1);

namespace App\Domain\Product\Contracts;

// use App\Models\Product; // REMOVED
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * ProductReader - Domain Contract for Product Data Retrieval
 *
 * PORT: Defines how the Application Layer retrieves product data.
 * ADAPTER: Implemented by Infrastructure layer (EloquentProductReader).
 */
interface ProductReader
{
    public function getInventory(string $tenantId): Collection;

    public function getLowStockProducts(string $tenantId): Collection;

    public function search(string $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Find a product by ID, ensuring tenant scope.
     */
    public function find(string $tenantId, int $productId): ?object;
}
