<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

use App\Application\Common\Interfaces\IdempotentCommandInterface;
use OpenApi\Attributes as OA;

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
#[OA\Schema(
    schema: 'CreateOrderCommand',
    title: 'Create Order Command',
    description: 'Command to place a new order',
    required: ['tenant_id', 'customer_id', 'items', 'currency']
)]
final readonly class CreateOrderCommand implements IdempotentCommandInterface
{
    /**
     * @param  array<int, array{product_id: int, quantity: int, options?: array, notes?: string}>  $items
     */
    public function __construct(
        #[OA\Property(property: 'tenant_id', type: 'string', format: 'uuid', example: '019f8c00-0000-7000-8000-000000000001')]
        public string $tenantId,

        #[OA\Property(property: 'customer_id', type: 'integer', example: 101, description: 'Registered User ID')]
        public string|int $customerId,

        #[OA\Property(property: 'currency', type: 'string', example: 'HNL', minLength: 3, maxLength: 3)]
        public string $currency,

        #[OA\Property(property: 'customer_name', type: 'string', example: 'Juan Perez', nullable: true)]
        public ?string $customerName = null,

        #[OA\Property(property: 'customer_phone', type: 'string', example: '99999999', nullable: true)]
        public ?string $customerPhone = null,

        #[OA\Property(property: 'customer_email', type: 'string', example: 'juan@example.com', nullable: true)]
        public ?string $customerEmail = null,

        #[OA\Property(property: 'customer_address', type: 'string', example: 'Col. Kennedy', nullable: true)]
        public ?string $customerAddress = null,

        #[OA\Property(property: 'customer_notes', type: 'string', example: 'Deliver after 5PM', nullable: true)]
        public ?string $customerNotes = null,

        #[OA\Property(property: 'delivery_type', type: 'string', example: 'pickup')]
        public string $deliveryType = 'pickup',

        #[OA\Property(property: 'payment_method', type: 'string', example: 'cash')]
        public string $paymentMethod = 'pending',

        #[OA\Property(property: 'items',
            type: 'array',
            description: 'List of products',
            items: new OA\Items(
                type: 'object',
                required: ['product_id', 'quantity'],
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 101),
                    new OA\Property(property: 'quantity', type: 'integer', example: 2),
                ]
            )
        )]
        public array $items = [],

        #[OA\Property(property: 'coupon_code', type: 'string', example: 'WELCOME20', nullable: true)]
        public ?string $couponCode = null,

        // Context - Strict Scalar ID only.
        public string|int|null $requestorId = null,

        // Explicit Idempotency
        public ?string $idempotencyKey = null
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

        return md5('create_order_'.$payload);
    }
}
