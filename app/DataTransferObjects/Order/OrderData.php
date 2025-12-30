<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Order;

use App\Exceptions\InvalidBusinessRuleException;
use App\Exceptions\MissingRequiredDataException;
use App\Exceptions\PayloadTooLargeException;
use App\ValueObjects\Money;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * OrderData - Fail-Fast Composite DTO for Checkout Payloads
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FIRST LINE OF DEFENSE: Data Integrity Before Business Logic            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This DTO acts as a FIREWALL between untrusted HTTP input and your      ║
 * ║  Service Layer. It guarantees that by the time data reaches the         ║
 * ║  OrderService, it is:                                                   ║
 * ║                                                                          ║
 * ║    ✓ Complete (no missing required fields)                              ║
 * ║    ✓ Valid (passes business rule checks)                                ║
 * ║    ✓ Typed (Money objects, not raw integers)                            ║
 * ║    ✓ Bounded (payload size within safe limits)                          ║
 * ║                                                                          ║
 * ║  FAIL-FAST PRINCIPLE:                                                   ║
 * ║    A 422 Unprocessable Entity response is infinitely better than        ║
 * ║    silently processing an order with $0.00 price or -5 quantity.        ║
 * ║    In financial systems: CRASH > CORRUPTION.                            ║
 * ║                                                                          ║
 * ║  SECURITY:                                                              ║
 * ║    - Max 2000 items (DoS protection)                                   ║
 * ║    - Server-side total calculation (never trust client math)           ║
 * ║    - All monetary values are Money objects (no float errors)           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @implements Arrayable<string, mixed>
 * @throws MissingRequiredDataException When required fields are missing
 * @throws InvalidBusinessRuleException When values violate business rules
 * @throws PayloadTooLargeException When items array exceeds safe limits
 */
final readonly class OrderData implements Arrayable, \JsonSerializable
{
    /**
     * Maximum number of line items allowed.
     * Protects against memory exhaustion DoS attacks.
     */
    private const MAX_ITEMS = 2000;

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
    ) {}

    /**
     * Hydrate from HTTP Request with STRICT validation.
     *
     * @throws MissingRequiredDataException When required fields are missing
     * @throws InvalidBusinessRuleException When values violate business rules
     * @throws PayloadTooLargeException When items exceed MAX_ITEMS limit
     */
    public static function fromRequest(Request $request): self
    {
        // Use validated() if available (from FormRequest), else raw input
        $data = method_exists($request, 'validated') 
            ? $request->validated() 
            : $request->all();
            
        return self::fromArray($data);
    }

    /**
     * Hydrate from array with STRICT validation.
     *
     * @param array<string, mixed> $data
     * @throws MissingRequiredDataException When required fields are missing
     * @throws InvalidBusinessRuleException When values violate business rules
     * @throws PayloadTooLargeException When items exceed MAX_ITEMS limit
     */
    public static function fromArray(array $data): self
    {
        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: Payload Size Validation (DoS Protection)
        // ═══════════════════════════════════════════════════════════════════════
        $rawItems = $data['items'] ?? null;
        
        if ($rawItems === null || !is_array($rawItems)) {
            throw MissingRequiredDataException::forField('items', 'OrderData');
        }

        $itemCount = count($rawItems);
        
        if ($itemCount === 0) {
            throw InvalidBusinessRuleException::emptyItems();
        }

        if ($itemCount > self::MAX_ITEMS) {
            throw PayloadTooLargeException::itemsExceeded($itemCount, self::MAX_ITEMS);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: Customer Data Validation
        // ═══════════════════════════════════════════════════════════════════════
        $customerEmail = $data['customer_email'] ?? $data['email'] ?? null;
        if (empty($customerEmail)) {
            throw MissingRequiredDataException::forField('customer_email', 'OrderData');
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 3: Hydrate Items (Fail-Fast on each item)
        // ═══════════════════════════════════════════════════════════════════════
        // Validate each item is an array before processing
        $items = collect();
        foreach ($rawItems as $index => $item) {
            if (!is_array($item)) {
                throw InvalidBusinessRuleException::custom(
                    "Item at index {$index} is not a valid array",
                    ['index' => $index, 'type' => gettype($item)]
                );
            }
            $items->push(OrderItemData::fromArray($item));
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 4: Calculate Totals (NEVER trust client-side calculations)
        // ═══════════════════════════════════════════════════════════════════════
        $calculatedSubtotal = $items->reduce(
            static fn(Money $carry, OrderItemData $item): Money => $carry->add($item->subtotal),
            Money::zero()
        );

        $taxRate = isset($data['tax_rate']) ? (float) $data['tax_rate'] : 15.0;
        
        if ($taxRate < 0 || $taxRate > 100) {
            throw InvalidBusinessRuleException::custom(
                "Invalid tax rate: {$taxRate}%. Must be between 0 and 100.",
                ['tax_rate' => $taxRate]
            );
        }

        // CRITICAL: Use multiplyByPercentage, NOT multiply() which truncates floats!
        $taxAmount = $calculatedSubtotal->multiplyByPercentage($taxRate);

        $shipping = self::extractMoney($data, 'shipping', 'shipping_cents', 0);
        $discount = self::extractMoney($data, 'discount', 'discount_cents', 0);

        // Validate discount doesn't exceed subtotal
        if ($discount->cents() > $calculatedSubtotal->cents()) {
            throw InvalidBusinessRuleException::custom(
                "Discount cannot exceed subtotal.",
                ['discount' => $discount->cents(), 'subtotal' => $calculatedSubtotal->cents()]
            );
        }

        $total = $calculatedSubtotal->add($taxAmount)->add($shipping)->subtract($discount);

        return new self(
            customerEmail: (string) $customerEmail,
            customerName: (string) ($data['customer_name'] ?? $data['name'] ?? 'Guest'),
            customerPhone: isset($data['customer_phone']) ? (string) $data['customer_phone'] : 
                          (isset($data['phone']) ? (string) $data['phone'] : null),
            items: $items,
            subtotal: $calculatedSubtotal,
            tax: $taxAmount,
            shipping: $shipping,
            discount: $discount,
            total: $total,
            taxRate: $taxRate,
            shippingMethod: isset($data['shipping_method']) ? (string) $data['shipping_method'] : null,
            shippingAddress: isset($data['shipping_address']) ? (string) $data['shipping_address'] : null,
            billingAddress: isset($data['billing_address']) ? (string) $data['billing_address'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            couponCode: isset($data['coupon_code']) ? (string) $data['coupon_code'] : null,
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

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private static function extractMoney(array $data, string $key, string $centsFallback, int $default): Money
    {
        if (isset($data[$key]) && $data[$key] instanceof Money) {
            return $data[$key];
        }

        $cents = $data[$centsFallback] ?? $data[$key] ?? $default;
        return Money::fromCents((int) $cents);
    }
}
