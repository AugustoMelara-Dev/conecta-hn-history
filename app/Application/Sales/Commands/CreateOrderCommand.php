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
final readonly class CreateOrderCommand implements \App\Application\Shared\Contracts\VersionedCommandInterface, IdempotentCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    /**
     * @param  array<int, array{product_id: int, quantity: int, options?: array, notes?: string}>  $items
     */
    public function __construct(
        public string $tenantId,
        public ?string $locationId,
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
        public ?string $sessionId = null,

        // Explicit Idempotency
        public ?string $idempotencyKey = null,

        // Schema Version for Upcasting
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        if ($this->idempotencyKey) {
            return $this->idempotencyKey;
        }

        $payload = json_encode([
            'tenant' => $this->tenantId,
            'location' => $this->locationId,
            'customer' => $this->customerId,
            'items' => $this->items,
            'total_items' => count($this->items),
            'coupon' => $this->couponCode,
        ]);

        return hash('xxh128', 'create_order_'.$payload);
    }
}
