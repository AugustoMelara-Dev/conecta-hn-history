<?php

declare(strict_types=1);

namespace App\Application\Orders\Commands;

final readonly class CreateOrderCommand
{
    /**
     * @param  array<int, array{product_id: int, quantity: int, options?: array<int, mixed>, notes?: string|null}>  $items
     */
    public function __construct(
        public string $tenantId,
        public ?string $actorId,
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
        public bool $publiclySellableOnly = false,
        public ?string $requestedCurrency = null,
    ) {}
}
