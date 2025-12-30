<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Order;

use App\ValueObjects\Money;
use Illuminate\Contracts\Support\Arrayable;

/**
 * OrderItemData - Line Item DTO for Order Composition
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Composite DTO Pattern                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This DTO represents a single line item in an order.                    ║
 * ║  It is composed into OrderData as a typed Collection.                   ║
 * ║                                                                          ║
 * ║  FINANCIAL SAFETY:                                                      ║
 * ║    All monetary values use Money Value Object (integer cents).          ║
 * ║    No floating-point calculations = no precision errors.               ║
 * ║                                                                          ║
 * ║  HYDRATION from Request:                                                ║
 * ║    POST /checkout { items: [{product_id: 1, quantity: 2, price: 500}] } ║
 * ║    → OrderItemData::fromArray() converts 500 → Money::fromCents(500)    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class OrderItemData implements Arrayable, \JsonSerializable
{
    public function __construct(
        public int $productId,
        public string $productName,
        public string $productSku,
        public int $quantity,
        public Money $unitPrice,
        public Money $subtotal,
        public ?string $notes = null,
        public ?int $variantId = null,
        public ?string $variantName = null,
    ) {
        if ($this->quantity < 1) {
            throw new \InvalidArgumentException('OrderItemData: quantity must be at least 1');
        }
    }

    /**
     * Hydrate from request/database array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $unitPrice = $data['unit_price'] instanceof Money
            ? $data['unit_price']
            : Money::fromCents((int) ($data['unit_price'] ?? $data['price'] ?? 0));

        $quantity = (int) ($data['quantity'] ?? 1);
        
        $subtotal = $data['subtotal'] instanceof Money
            ? $data['subtotal']
            : $unitPrice->multiply($quantity);

        return new self(
            productId: (int) $data['product_id'],
            productName: $data['product_name'] ?? $data['name'] ?? 'Unknown Product',
            productSku: $data['product_sku'] ?? $data['sku'] ?? '',
            quantity: $quantity,
            unitPrice: $unitPrice,
            subtotal: $subtotal,
            notes: $data['notes'] ?? null,
            variantId: isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            variantName: $data['variant_name'] ?? null,
        );
    }

    /**
     * Serialize for API/Inertia response.
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_sku' => $this->productSku,
            'quantity' => $this->quantity,
            'unit_price' => [
                'cents' => $this->unitPrice->cents(),
                'formatted' => $this->unitPrice->formatted(),
            ],
            'subtotal' => [
                'cents' => $this->subtotal->cents(),
                'formatted' => $this->subtotal->formatted(),
            ],
            'notes' => $this->notes,
            'variant_id' => $this->variantId,
            'variant_name' => $this->variantName,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
