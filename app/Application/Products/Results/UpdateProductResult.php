<?php

declare(strict_types=1);

namespace App\Application\Products\Results;

use App\Domain\Product\Entities\ProductEntity;

/**
 * UpdateProductResult - Immutable Output Contract for Product Update
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  AUDIT FRIENDLY: Includes changedAttributes for UI change notifications ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateProductResult
{
    /**
     * @param  int  $id  Product ID
     * @param  string  $sku  Product SKU
     * @param  array<string>  $changedAttributes  List of modified field names
     */
    public function __construct(
        public int $id,
        public string $sku,
        public array $changedAttributes,
    ) {}

    /**
     * Factory method to hydrate from Domain Entity / Eloquent Model.
     *
     * @param  array<string>  $changedAttributes  Fields that were modified
     */
    public static function fromEntity(ProductEntity $product, array $changedAttributes = []): self
    {
        return new self(
            id: $product->id ?? 0,
            sku: $product->sku,
            changedAttributes: $changedAttributes,
        );
    }
}
