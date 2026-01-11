<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

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
 */
#[OA\Schema(
    schema: "CreateOrderCommand",
    title: "Create Order Command",
    description: "Command to place a new order",
    required: ["tenant_id", "customer_id", "items", "currency"]
)]
final readonly class CreateOrderCommand
{
    /**
     * @param int $tenantId
     * @param int $customerId
     * @param string $currency
     * @param string|null $customerName
     * @param string|null $customerPhone
     * @param string|null $customerEmail
     * @param string|null $customerAddress
     * @param string|null $customerNotes
     * @param string $deliveryType
     * @param string $paymentMethod
     * @param array<int, array{product_id: int, quantity: int, options?: array, notes?: string}> $items
     * @param string|null $couponCode
     * @param int|null $requestorId (ID of the user placing the order, or null for system/guest)
     */
    public function __construct(
        #[OA\Property(property: "tenant_id", type: "integer", example: 1)]
        public int $tenantId,

        #[OA\Property(property: "customer_id", type: "integer", example: 101, description: "Registered User ID")]
        public int $customerId,

        #[OA\Property(property: "currency", type: "string", example: "HNL", minLength: 3, maxLength: 3)]
        public string $currency,

        #[OA\Property(property: "customer_name", type: "string", example: "Juan Perez", nullable: true)]
        public ?string $customerName = null,

        #[OA\Property(property: "customer_phone", type: "string", example: "99999999", nullable: true)]
        public ?string $customerPhone = null,

        #[OA\Property(property: "customer_email", type: "string", example: "juan@example.com", nullable: true)]
        public ?string $customerEmail = null,

        #[OA\Property(property: "customer_address", type: "string", example: "Col. Kennedy", nullable: true)]
        public ?string $customerAddress = null,

        #[OA\Property(property: "customer_notes", type: "string", example: "Deliver after 5PM", nullable: true)]
        public ?string $customerNotes = null,

        #[OA\Property(property: "delivery_type", type: "string", example: "pickup")]
        public string $deliveryType = 'pickup',

        #[OA\Property(property: "payment_method", type: "string", example: "cash")]
        public string $paymentMethod = 'pending',

        #[OA\Property(property: "items", 
            type: "array", 
            description: "List of products",
            items: new OA\Items(
                type: "object",
                required: ["product_id", "quantity"],
                properties: [
                    new OA\Property(property: "product_id", type: "integer", example: 101),
                    new OA\Property(property: "quantity", type: "integer", example: 2),
                ]
            )
        )]
        public array $items = [],

        #[OA\Property(property: "coupon_code", type: "string", example: "WELCOME20", nullable: true)]
        public ?string $couponCode = null,

        // Context - Strict Scalar ID only.
        public ?int $requestorId = null
    ) {}
}
