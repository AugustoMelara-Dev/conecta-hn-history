<?php

declare(strict_types=1);

namespace App\Application\Sales\Commands;

use App\Http\Requests\StoreOrderRequest;
use OpenApi\Attributes as OA;

/**
 * CreateOrderCommand - Intent for Order Creation
 *
 * Encapsulates all data required to create an order.
 * Decouples the Application layer from HTTP Request objects.
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
     * @param string $customerName
     * @param string $customerPhone
     * @param string|null $customerEmail
     * @param string|null $customerAddress
     * @param string|null $customerNotes
     * @param string $deliveryType
     * @param string $paymentMethod
     * @param array<int, array{id: int, quantity: int, options?: array, notes?: string}> $items
     * @param string|null $couponCode
     * @param mixed $requestor (Optional - user placing the order)
     */
    public function __construct(
        #[OA\Property(property: "customer_id", type: "integer", example: 101, description: "Registered User ID")]
        public int $customerId,

        #[OA\Property(property: "currency", type: "string", example: "HNL", minLength: 3, maxLength: 3)]
        public string $currency,

        #[OA\Property(property: "customer_name", type: "string", example: "Juan Perez", nullable: true)]
        public ?string $customerName = null, // Made optional as customerId is primary

        #[OA\Property(property: "customer_phone", type: "string", example: "99999999", nullable: true)]
        public ?string $customerPhone = null,
        
        // ... kept other fields optional ...
        
        #[OA\Property(property: "items", 
            type: "array", 
            description: "List of products",
            items: new OA\Items(
                type: "object",
                required: ["product_id", "quantity"], // Updated from id to product_id per validator spec
                properties: [
                    new OA\Property(property: "product_id", type: "integer", example: 101),
                    new OA\Property(property: "quantity", type: "integer", example: 2),
                ]
            )
        )]
        public array $items = [],

        #[OA\Property(property: "coupon_code", type: "string", example: "WELCOME20", nullable: true)]
        public ?string $couponCode = null,

        // Context
        public mixed $requestor = null
    ) {}

    /**
     * Factory method to create Command from validated FormRequest.
     */
    public static function fromRequest(StoreOrderRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            tenantId: (int) $validated['tenant_id'],
            customerId: (int) ($validated['customer_id'] ?? 0),
            currency: (string) ($validated['currency'] ?? 'HNL'),
            customerName: $validated['customer_name'] ?? null,
            customerPhone: $validated['customer_phone'] ?? null,
            customerEmail: $validated['customer_email'] ?? null,
            customerAddress: $validated['customer_address'] ?? null,
            customerNotes: $validated['customer_notes'] ?? null,
            deliveryType: (string) ($validated['delivery_type'] ?? 'pickup'),
            paymentMethod: $validated['payment_method'] ?? 'pending',
            items: (array) $validated['items'],
            couponCode: $validated['coupon_code'] ?? null,
            requestor: $request->user()
        );
    }
}
