<?php

declare(strict_types=1);

namespace App\Application\Sales\DTOs;

final readonly class CreatePublicOrderData
{
    /**
     * @param  array<int, array{product_id: int, quantity: int, options?: array<int, mixed>, notes?: string|null}>  $items
     */
    public function __construct(
        public string $tenantId,
        public string $idempotencyKey,
        public string $customerName,
        public string $customerPhone,
        public ?string $customerEmail,
        public ?string $customerAddress,
        public ?string $customerNotes,
        public string $deliveryType,
        public string $paymentMethod,
        public array $items,
        public ?string $couponCode = null,
        public ?string $userId = null,
        public string $source = 'web',
    ) {}

    public function payloadHash(): string
    {
        return hash('sha256', json_encode([
            'tenant_id' => $this->tenantId,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'customer_email' => $this->customerEmail,
            'customer_address' => $this->customerAddress,
            'customer_notes' => $this->customerNotes,
            'delivery_type' => $this->deliveryType,
            'payment_method' => $this->paymentMethod,
            'items' => $this->items,
            'coupon_code' => $this->couponCode,
            'user_id' => $this->userId,
            'source' => $this->source,
        ], JSON_THROW_ON_ERROR));
    }
}
