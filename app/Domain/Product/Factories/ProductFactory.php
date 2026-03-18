<?php

declare(strict_types=1);

namespace App\Domain\Product\Factories;

use App\Domain\Product\Entities\ProductEntity;
use App\Domain\Shared\Contracts\ClockInterface;

final readonly class ProductFactory
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function createNew(
        string $tenantId,
        string $name,
        string $description,
        int $price, // Expecting cents
        int $stock,
        ?string $sku = null,
        ?int $categoryId = null,
        ?string $imagePath = null,
        bool $isVisible = false
    ): ProductEntity {
        // Enforce Logic: Price cannot be negative
        if ($price < 0) {
            throw new \InvalidArgumentException('Price cannot be negative');
        }

        // Domain Logic: Generate Default SKU if missing
        $finalSku = $sku ?? $this->generateSku($name, $tenantId);

        return new ProductEntity(
            id: null,
            tenantId: $tenantId,
            sku: $finalSku,
            name: $name,
            description: $description,
            price: $price,
            stockQuantity: $stock,
            categoryId: $categoryId,
            imagePath: $imagePath,
            isVisible: $isVisible,
            createdAt: $this->clock->now(),
            updatedAt: $this->clock->now()
        );
    }

    private function generateSku(string $name, string $tenantId): string
    {
        // Simple logic: UPPERCASE-SLUG-RANDOM
        // In a real system, might call a SKU Generator Service to ensure uniqueness.
        // For this refactor, we encapsulate the logic here instead of Handler.
        $slug = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));

        return substr($slug, 0, 8).'-'.uniqid();
    }
}
