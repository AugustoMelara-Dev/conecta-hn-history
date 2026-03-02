<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;

/**
 * CreateOrderCommand - Intent for Order Creation
 *
 * Encapsulates all data required to create an order.
 * Decouples the Application layer from HTTP Request objects.
 *
 * INVARIANTS:
 * - Immutable (Final Readonly)
 * - Strict Scalar Typing (No generic objects)
 * - No HTTP dependencies
 * - Idempotency Aware
 */
final readonly class CreateOrderCommand implements IdempotentCommandInterface
{
    /**
     * @param  array<int, array{product_id: int, quantity: int, options?: array, notes?: string}>  $items
     */
    public function __construct(
        public string $tenantId,
        public string $customerId,
        public string $currency,
        public ?string $customerName = null,
        #[\SensitiveParameter] public ?string $customerPhone = null,
        #[\SensitiveParameter] public ?string $customerEmail = null,
        #[\SensitiveParameter] public ?string $customerAddress = null,
        public ?string $customerNotes = null,
        public string $deliveryType = 'pickup',
        public string $paymentMethod = 'pending',
        public array $items = [],
        public ?string $couponCode = null,

        // Context - Strict Scalar ID only.
        public ?string $requestorId = null,

        // Explicit Idempotency
        public ?string $idempotencyKey = null,

        // Schema Version for Upcasting
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        // Use explicit key if provided (best practice for frontend apps)
        if ($this->idempotencyKey) {
            return $this->idempotencyKey;
        }

        // Fallback: Generate generic hash based on critical parameters
        // To prevent collisions on legit same-orders, frontend MUST send a unique key.
        // Hashing content is a "lazy" dedupe but risky if user unintentionally orders twice.
        // However, for "Double Click" protection, this is effective within a short window.
        $payload = json_encode([
            'tenant' => $this->tenantId,
            'customer' => $this->customerId,
            'items' => $this->items,
            'total_items' => count($this->items),
            'coupon' => $this->couponCode,
        ]);

        return hash('xxh128', 'create_order_'.$payload);
    }
}
