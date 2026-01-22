<?php

declare(strict_types=1);

namespace App\Application\Products\Results;

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
     *
     * @param  object  $product  The Product entity
     */
    public static function fromEntity(object $product): self
    {
        return new self(
            id: $product->id,
            sku: $product->sku,
            name: $product->name,
            price: (float) $product->price,
            isVisible: (bool) $product->is_visible,
            imagePath: $product->image_path ?? null,
        );
    }
}
