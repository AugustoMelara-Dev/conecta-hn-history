<?php

declare(strict_types=1);

namespace App\Application\DTOs\Orders;

use App\Http\Requests\StoreOrderRequest;
use OpenApi\Attributes as OA;

/**
 * CreateOrderData - Immutable DTO for Order Creation
 *
 * Encapsulates all data required to create an order.
 * Decouples the Application layer from HTTP Request objects.
 */
#[OA\Schema(
    schema: "CreateOrderData",
    title: "Create Order Payload",
    description: "Data required to place a new order",
    required: ["tenant_id", "customer_name", "customer_phone", "delivery_type", "items"]
)]
final readonly class CreateOrderData
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
     */
    public function __construct(
        #[OA\Property(property: "tenant_id", type: "integer", example: 1, description: "ID of the tenant shop")]
        public int $tenantId,

        #[OA\Property(property: "customer_name", type: "string", example: "Juan Perez")]
        public string $customerName,

        #[OA\Property(property: "customer_phone", type: "string", example: "99999999")]
        public string $customerPhone,

        #[OA\Property(property: "customer_email", type: "string", format: "email", example: "juan@example.com", nullable: true)]
        public ?string $customerEmail,

        #[OA\Property(property: "customer_address", type: "string", example: "Col. Kennedy, bloque 3", nullable: true)]
        public ?string $customerAddress,

        #[OA\Property(property: "customer_notes", type: "string", example: "Sin cebolla", nullable: true)]
        public ?string $customerNotes,

        #[OA\Property(property: "delivery_type", type: "string", enum: ["pickup", "delivery"], example: "delivery")]
        public string $deliveryType,

        #[OA\Property(property: "payment_method", type: "string", enum: ["cash", "bank_transfer", "card"], example: "cash")]
        public string $paymentMethod,

        #[OA\Property(
            property: "items", 
            type: "array", 
            description: "List of products",
            items: new OA\Items(
                type: "object",
                required: ["id", "quantity"],
                properties: [
                    new OA\Property(property: "id", type: "integer", example: 101),
                    new OA\Property(property: "quantity", type: "integer", example: 2),
                    new OA\Property(property: "options", type: "array", items: new OA\Items(type: "integer"), nullable: true),
                    new OA\Property(property: "notes", type: "string", nullable: true)
                ]
            )
        )]
        public array $items,

        #[OA\Property(property: "coupon_code", type: "string", example: "WELCOME20", nullable: true)]
        public ?string $couponCode
    ) {}

    /**
     * Factory method to create DTO from validated FormRequest.
     */
    public static function fromRequest(StoreOrderRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            tenantId: (int) $validated['tenant_id'],
            customerName: (string) $validated['customer_name'],
            customerPhone: (string) $validated['customer_phone'],
            customerEmail: $validated['customer_email'] ?? null,
            customerAddress: $validated['customer_address'] ?? null,
            customerNotes: $validated['customer_notes'] ?? null,
            deliveryType: (string) $validated['delivery_type'],
            paymentMethod: $validated['payment_method'] ?? 'pending',
            items: (array) $validated['items'],
            couponCode: $validated['coupon_code'] ?? null
        );
    }

    /**
     * Generate a unique lock key for idempotency.
     */
    public function generateLockKey(): string
    {
        $cartHash = md5(json_encode($this->items));
        return "order_lock:{$this->tenantId}:{$this->customerPhone}:{$cartHash}";
    }
}
