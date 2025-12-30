<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Order;

use App\ValueObjects\Money;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * OrderData - Composite DTO for Checkout Payload
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HYDRATION STRATEGY: Converting Request Arrays to Typed Collections     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  HTTP Request (raw):                                                    ║
 * ║    POST /checkout {                                                     ║
 * ║      "customer_email": "user@example.com",                              ║
 * ║      "items": [                                                         ║
 * ║        {"product_id": 1, "quantity": 2, "price": 5000},                 ║
 * ║        {"product_id": 5, "quantity": 1, "price": 15000}                 ║
 * ║      ]                                                                  ║
 * ║    }                                                                    ║
 * ║                                                                          ║
 * ║  After Hydration (OrderData):                                           ║
 * ║    - items: Collection<OrderItemData> (typed, not raw arrays)           ║
 * ║    - subtotal: Money (calculated from items)                            ║
 * ║    - tax: Money (calculated from taxRate)                               ║
 * ║    - total: Money (subtotal + tax + shipping)                           ║
 * ║                                                                          ║
 * ║  INVARIANT ENFORCEMENT:                                                 ║
 * ║    - items cannot be empty (throws InvalidArgumentException)            ║
 * ║    - All Money fields use integer cents (no float errors)               ║
 * ║    - Financial totals are calculated, not trusted from input            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class OrderData implements Arrayable, \JsonSerializable
{
    /**
     * @param Collection<int, OrderItemData> $items
     */
    public function __construct(
        public string $customerEmail,
        public string $customerName,
        public ?string $customerPhone,
        public Collection $items,
        public Money $subtotal,
        public Money $tax,
        public Money $shipping,
        public Money $discount,
        public Money $total,
        public float $taxRate,
        public ?string $shippingMethod = null,
        public ?string $shippingAddress = null,
        public ?string $billingAddress = null,
        public ?string $notes = null,
        public ?string $couponCode = null,
        public ?string $paymentMethodToken = null,
    ) {
        if ($this->items->isEmpty()) {
            throw new \InvalidArgumentException('OrderData: items cannot be empty');
        }
    }

    /**
     * Hydrate from HTTP Request.
     *
     * Converts nested arrays into strongly-typed DTO collections.
     */
    public static function fromRequest(Request $request): self
    {
        $validated = $request->validated();
        return self::fromArray($validated);
    }

    /**
     * Hydrate from array (database or API payload).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // ═══════════════════════════════════════════════════════════════════════
        // HYDRATE NESTED ITEMS: array<array> → Collection<OrderItemData>
        // ═══════════════════════════════════════════════════════════════════════
        $rawItems = $data['items'] ?? [];
        if (empty($rawItems)) {
            throw new \InvalidArgumentException('OrderData: items array is required');
        }

        $items = collect($rawItems)->map(fn(array $item) => OrderItemData::fromArray($item));

        // ═══════════════════════════════════════════════════════════════════════
        // CALCULATE TOTALS: Never trust client-side calculations
        // ═══════════════════════════════════════════════════════════════════════
        $calculatedSubtotal = $items->reduce(
            fn(Money $carry, OrderItemData $item) => $carry->add($item->subtotal),
            Money::zero()
        );

        $taxRate = (float) ($data['tax_rate'] ?? 15.0);
        $taxAmount = $calculatedSubtotal->multiply($taxRate / 100);

        $shipping = isset($data['shipping']) && $data['shipping'] instanceof Money
            ? $data['shipping']
            : Money::fromCents((int) ($data['shipping_cents'] ?? $data['shipping'] ?? 0));

        $discount = isset($data['discount']) && $data['discount'] instanceof Money
            ? $data['discount']
            : Money::fromCents((int) ($data['discount_cents'] ?? $data['discount'] ?? 0));

        $total = $calculatedSubtotal->add($taxAmount)->add($shipping)->subtract($discount);

        return new self(
            customerEmail: $data['customer_email'] ?? $data['email'],
            customerName: $data['customer_name'] ?? $data['name'] ?? 'Guest',
            customerPhone: $data['customer_phone'] ?? $data['phone'] ?? null,
            items: $items,
            subtotal: $calculatedSubtotal,
            tax: $taxAmount,
            shipping: $shipping,
            discount: $discount,
            total: $total,
            taxRate: $taxRate,
            shippingMethod: $data['shipping_method'] ?? null,
            shippingAddress: $data['shipping_address'] ?? null,
            billingAddress: $data['billing_address'] ?? null,
            notes: $data['notes'] ?? null,
            couponCode: $data['coupon_code'] ?? null,
            paymentMethodToken: $data['payment_method_token'] ?? $data['token'] ?? null,
        );
    }

    /**
     * Serialize for API/Inertia response.
     */
    public function toArray(): array
    {
        return [
            'customer_email' => $this->customerEmail,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'items' => $this->items->map(fn(OrderItemData $item) => $item->toArray())->all(),
            'item_count' => $this->items->count(),
            'subtotal' => [
                'cents' => $this->subtotal->cents(),
                'formatted' => $this->subtotal->formatted(),
            ],
            'tax' => [
                'cents' => $this->tax->cents(),
                'formatted' => $this->tax->formatted(),
                'rate' => $this->taxRate,
            ],
            'shipping' => [
                'cents' => $this->shipping->cents(),
                'formatted' => $this->shipping->formatted(),
                'method' => $this->shippingMethod,
            ],
            'discount' => [
                'cents' => $this->discount->cents(),
                'formatted' => $this->discount->formatted(),
                'coupon_code' => $this->couponCode,
            ],
            'total' => [
                'cents' => $this->total->cents(),
                'formatted' => $this->total->formatted(),
            ],
            'shipping_address' => $this->shippingAddress,
            'billing_address' => $this->billingAddress,
            'notes' => $this->notes,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONVENIENCE METHODS
    // ═══════════════════════════════════════════════════════════════════════

    public function hasDiscount(): bool
    {
        return $this->discount->cents() > 0;
    }

    public function hasCoupon(): bool
    {
        return !empty($this->couponCode);
    }

    public function getTotalQuantity(): int
    {
        return $this->items->sum(fn(OrderItemData $item) => $item->quantity);
    }
}
