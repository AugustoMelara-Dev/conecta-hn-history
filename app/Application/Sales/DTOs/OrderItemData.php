<?php

declare(strict_types=1);

namespace App\Application\Sales\Results;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * OrderItemData - Immutable Data Object for Order Lines
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GRANULARITY: Represents a single line item within an order context.    ║
 * ║  IMMUTABILITY: Readonly to prevent side effects in the Application.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
#[TypeScript]
final readonly class OrderItemData
{
    public function __construct(
        public int $productId,
        public string $productName,
        public int $quantity,
        public float $unitPrice,
        public float $total,
    ) {}

    /**
     * Factory method from Associative Array (e.g. from JSON or Eloquent Relation).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            productName: (string) $data['product_name'], // Snapshot name
            quantity: (int) $data['quantity'],
            unitPrice: (float) $data['price'],
            total: (float) $data['line_total'],
        );
    }
}
