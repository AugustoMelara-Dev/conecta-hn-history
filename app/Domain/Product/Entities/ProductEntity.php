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
        public int $tenantId,
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
}
