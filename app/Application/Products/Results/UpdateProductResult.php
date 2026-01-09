<?php

declare(strict_types=1);

namespace App\Application\Products\Results;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UpdateProductResult - Immutable Output Contract for Product Update
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  AUDIT FRIENDLY: Includes changedAttributes for UI change notifications ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
#[TypeScript]
final readonly class UpdateProductResult
{
    /**
     * @param int $id Product ID
     * @param string $sku Product SKU
     * @param array<string> $changedAttributes List of modified field names
     */
    public function __construct(
        public int $id,
        public string $sku,
        public array $changedAttributes,
    ) {}

    /**
     * Factory method to hydrate from Domain Entity / Eloquent Model.
     *
     * @param object $product The updated product
     * @param array<string> $changedAttributes Fields that were modified
     */
    public static function fromEntity(object $product, array $changedAttributes = []): self
    {
        return new self(
            id: $product->id,
            sku: $product->sku,
            changedAttributes: $changedAttributes,
        );
    }
}
