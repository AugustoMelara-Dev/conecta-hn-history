<?php

declare(strict_types=1);

namespace App\Domain\Product\Contracts;

// use App\Models\Product; // REMOVED
use App\Domain\Shared\Contracts\PaginatedResult;

/**
 * ProductReader - Domain Contract for Product Data Retrieval
 *
 * PORT: Defines how the Application Layer retrieves product data.
 * ADAPTER: Implemented by Infrastructure layer (EloquentProductReader).
 */
interface ProductReader
{
    public function getInventory(int $tenantId): array;

    public function getLowStockProducts(int $tenantId): array;

    public function search(int $tenantId, array $filters, int $perPage = 20): PaginatedResult;

    /**
     * Find a product by ID, ensuring tenant scope.
     */
    public function find(int $tenantId, int $productId): ?object;
}
