<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use DateTimeImmutable;

/**
 * OrderEntity - Aggregate Root for Sales Order
 *
 * FINANCIAL PRECISION (PCI-DSS): All monetary values stored as int (centavos).
 * e.g. $subtotal = 10000 represents 100.00 HNL
 * Infrastructure hydration: (int) round($model->subtotal * 100)
 */
final readonly class OrderEntity
{
    public function __construct(
        public ?int $id,
        public string $tenantId,
        public string $customerId,
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
        public float $taxAmount = 0,
        public float $deliveryFee = 0,
        public float $discountAmount = 0,
        public ?string $couponCode = null,
        /** @var array<int, array> $items Raw item data for now, ideally LineItemEntity[] */
        public array $items = []
    ) {}

    // setId() removed to enforce strict immutability (A1).
    // Infrastructure must hydrate $id via constructor at reconstitution time.
}
