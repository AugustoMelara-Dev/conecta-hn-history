<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Order;

use App\Exceptions\InvalidBusinessRuleException;
use App\Exceptions\MissingRequiredDataException;
use App\ValueObjects\Money;
use Illuminate\Contracts\Support\Arrayable;

/**
 * OrderItemData - Fail-Fast Line Item DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FAIL-FAST PHILOSOPHY: Crash > Corruption                               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  WRONG (Silent Corruption):                                             ║
 * ║    $price = (int) ($data['price'] ?? 0);  // null → 0 = FREE PRODUCTS! ║
 * ║                                                                          ║
 * ║  CORRECT (Fail-Fast):                                                   ║
 * ║    if (!isset($data['price'])) throw MissingRequiredDataException       ║
 * ║    if ($data['price'] < 0) throw InvalidBusinessRuleException           ║
 * ║                                                                          ║
 * ║  A controlled 400/422 error is infinitely better than silently          ║
 * ║  processing an order with $0.00 price or negative quantities.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @implements Arrayable<string, mixed>
 *
 * @throws MissingRequiredDataException When required fields are missing
 * @throws InvalidBusinessRuleException When values violate business rules
 */
final readonly class OrderItemData implements \JsonSerializable, Arrayable
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
    ) {}

    /**
     * Hydrate from array with STRICT validation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws MissingRequiredDataException When product_id, quantity, or price missing
     * @throws InvalidBusinessRuleException When quantity <= 0 or price < 0
     */
    public static function fromArray(array $data): self
    {
        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: Presence Validation (Fail-Fast)
        // ═══════════════════════════════════════════════════════════════════════
        self::assertRequiredFields($data);

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: Type Coercion (after presence confirmed)
        // ═══════════════════════════════════════════════════════════════════════
        $productId = (int) $data['product_id'];
        $quantity = (int) $data['quantity'];

        // Handle unit_price: can be Money object, int (cents), or array with 'cents' key
        $unitPriceCents = self::extractPriceCents($data, 'unit_price');

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 3: Business Rule Validation
        // ═══════════════════════════════════════════════════════════════════════
        if ($quantity < 1) {
            throw InvalidBusinessRuleException::invalidQuantity($quantity);
        }

        if ($unitPriceCents < 0) {
            throw InvalidBusinessRuleException::invalidPrice($unitPriceCents);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 4: Value Object Construction
        // ═══════════════════════════════════════════════════════════════════════
        $unitPrice = Money::fromCents($unitPriceCents);
        $subtotal = $unitPrice->multiply($quantity);

        return new self(
            productId: $productId,
            productName: self::requireString($data, 'product_name', 'name'),
            productSku: (string) ($data['product_sku'] ?? $data['sku'] ?? ''),
            quantity: $quantity,
            unitPrice: $unitPrice,
            subtotal: $subtotal,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            variantId: isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            variantName: isset($data['variant_name']) ? (string) $data['variant_name'] : null,
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

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE VALIDATION HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private static function assertRequiredFields(array $data): void
    {
        $missing = [];

        foreach (['product_id', 'quantity'] as $field) {
            if (! isset($data[$field])) {
                $missing[] = $field;
            }
        }

        // price can come as unit_price or price
        if (! isset($data['unit_price']) && ! isset($data['price'])) {
            $missing[] = 'unit_price';
        }

        if (! empty($missing)) {
            throw MissingRequiredDataException::forFields($missing, 'OrderItemData');
        }
    }

    private static function extractPriceCents(array $data, string $key): int
    {
        $value = $data[$key] ?? $data['price'] ?? null;

        if ($value instanceof Money) {
            return $value->cents();
        }

        if (is_array($value) && isset($value['cents'])) {
            return (int) $value['cents'];
        }

        return (int) $value;
    }

    private static function requireString(array $data, string $primary, string $fallback): string
    {
        if (isset($data[$primary])) {
            return (string) $data[$primary];
        }

        if (isset($data[$fallback])) {
            return (string) $data[$fallback];
        }

        throw MissingRequiredDataException::forField($primary, 'OrderItemData');
    }
}
