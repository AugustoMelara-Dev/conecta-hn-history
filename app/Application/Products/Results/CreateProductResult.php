<?php

declare(strict_types=1);

namespace App\Application\Products\Results;

use App\Domain\Product\Entities\ProductEntity;

/**
 * CreateProductResult - Immutable Output Contract for Product Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SEMANTIC CONTRACT: Exposes only necessary data to consumers.           ║
 * ║  DECOUPLED: No Eloquent attributes leak to API layer.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class CreateProductResult
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public float $price,
        public bool $isVisible,
        public ?string $imagePath,
    ) {}

    /**
     * Factory method to hydrate from Domain Entity / Eloquent Model.
     */
    public static function fromEntity(ProductEntity $product): self
    {
        return new self(
            id: $product->id ?? 0,
            sku: $product->sku,
            name: $product->name,
            price: $product->price / 100,
            isVisible: $product->isVisible,
            imagePath: $product->imagePath,
        );
    }
}
