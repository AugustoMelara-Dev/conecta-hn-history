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
    public function getInventory(int|string $tenantId): array;

    public function getLowStockProducts(int|string $tenantId): array;

    public function search(int|string $tenantId, array $filters, int $perPage = 20): PaginatedResult;

    /**
     * Find a product by ID, ensuring tenant scope.
     */
    public function find(int|string $tenantId, int $productId): ?object;
}
