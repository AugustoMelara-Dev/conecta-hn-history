<?php

declare(strict_types=1);

namespace App\Domain\Product\Entities;

use DateTimeImmutable;

/**
 * ProductEntity - Rich Domain Model for E-Commerce Product
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IMMUTABILITY RADICAL:                                                  ║
 * ║  - 'final readonly class' enforces immutability on all properties.      ║
 * ║  - Public properties for clean access (no getters).                     ║
 * ║  - Strict Types (int for price in cents).                               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ProductEntity
{
    public function __construct(
        public ?int $id,
        public string $tenantId,
        public string $sku,
        public string $name,
        public string $description,
        public int $price, // stored in cents
        public int $stockQuantity,
        public ?int $categoryId = null,
        public ?string $imagePath = null,
        public bool $isVisible = false,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function belongsToTenant(string $tenantId): bool
    {
        return $this->tenantId === $tenantId;
    }

    public function toggleVisibility(): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            sku: $this->sku,
            name: $this->name,
            description: $this->description,
            price: $this->price,
            stockQuantity: $this->stockQuantity,
            categoryId: $this->categoryId,
            imagePath: $this->imagePath,
            isVisible: ! $this->isVisible,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable
        );
    }

    public function withUpdatedDetails(
        ?string $sku,
        string $name,
        string $description,
        int $price,
        int $stockQuantity,
        ?int $categoryId,
        ?string $imagePath,
        bool $isVisible
    ): self {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            sku: $sku ?? $this->sku,
            name: $name,
            description: $description,
            price: $price,
            stockQuantity: $stockQuantity,
            categoryId: $categoryId,
            imagePath: $imagePath,
            isVisible: $isVisible,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable
        );
    }

    /**
     * @return list<string>
     */
    public function changedAttributesComparedTo(self $original): array
    {
        $changes = [];

        if ($this->sku !== $original->sku) {
            $changes[] = 'sku';
        }

        if ($this->name !== $original->name) {
            $changes[] = 'name';
        }

        if ($this->description !== $original->description) {
            $changes[] = 'description';
        }

        if ($this->price !== $original->price) {
            $changes[] = 'price';
        }

        if ($this->stockQuantity !== $original->stockQuantity) {
            $changes[] = 'stock';
        }

        if ($this->categoryId !== $original->categoryId) {
            $changes[] = 'category_id';
        }

        if ($this->imagePath !== $original->imagePath) {
            $changes[] = 'image_path';
        }

        if ($this->isVisible !== $original->isVisible) {
            $changes[] = 'is_visible';
        }

        return $changes;
    }
}
