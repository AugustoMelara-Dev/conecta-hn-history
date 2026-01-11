<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use DateTimeImmutable;

/**
 * OrderEntity - Aggregate Root for Sales Order
 */
final class OrderEntity
{
    public function __construct(
        public ?int $id,
        public int $tenantId,
        public int $customerId,
        public string $orderNumber,
        public string $customerName,
        public string $customerEmail,
        public string $currency,
        public float $subtotal,
        public float $total,
        public string $status,
        public DateTimeImmutable $createdAt,
        public ?string $customerPhone = null,
        public ?string $customerAddress = null,
        public ?string $customerNotes = null,
        public string $deliveryType = 'pickup',
        public string $paymentMethod = 'pending',
        public float $taxAmount = 0.0,
        public float $deliveryFee = 0.0,
        public float $discountAmount = 0.0,
        public ?string $couponCode = null,
        /** @var array<int, array> $items Raw item data for now, ideally LineItemEntity[] */
        public array $items = [] 
    ) {}

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
}
