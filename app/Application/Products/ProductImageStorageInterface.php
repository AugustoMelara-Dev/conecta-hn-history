<?php

declare(strict_types=1);

namespace App\Application\Products;

interface ProductImageStorageInterface
{
    /**
     * Validate and normalize one tenant-owned staging reference without
     * requiring the object to still exist (idempotent replay support).
     */
    public function stagedPath(string $tenantId, mixed $state): ?string;

    public function promote(
        string $tenantId,
        int $productId,
        string $operationKey,
        string $stagedPath,
    ): ProductImagePromotion;

    public function discardStaged(string $tenantId, string $stagedPath): void;

    public function discardPublished(string $tenantId, int $productId, ?string $publishedPath): void;
}
