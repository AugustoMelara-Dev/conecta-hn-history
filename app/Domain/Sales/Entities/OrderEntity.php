<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Domain\Sales\Exceptions\OrderCannotBeCancelledException;
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
        public ?string $locationId,
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
        public string $paymentStatus = 'pending',
        public float $taxAmount = 0,
        public float $deliveryFee = 0,
        public float $discountAmount = 0,
        public ?string $couponCode = null,
        public ?string $cancellationReason = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?int $cancelledById = null,
        public ?DateTimeImmutable $paidAt = null,
        /** @var array<int, array> $items Raw item data for now, ideally LineItemEntity[] */
        public array $items = []
    ) {}

    public function belongsToTenant(string $tenantId): bool
    {
        return $this->tenantId === $tenantId;
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, ['delivered', 'cancelled'], true);
    }

    public function cancel(string $reason, int $cancelledByUserId): self
    {
        if ($this->status === 'cancelled') {
            return $this;
        }

        if (! $this->canBeCancelled()) {
            throw new OrderCannotBeCancelledException(
                "Order #{$this->orderNumber} cannot be cancelled from status {$this->status}."
            );
        }

        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            locationId: $this->locationId,
            customerId: $this->customerId,
            orderNumber: $this->orderNumber,
            customerName: $this->customerName,
            customerEmail: $this->customerEmail,
            currency: $this->currency,
            subtotal: $this->subtotal,
            total: $this->total,
            status: 'cancelled',
            createdAt: $this->createdAt,
            customerPhone: $this->customerPhone,
            customerAddress: $this->customerAddress,
            customerNotes: $this->customerNotes,
            deliveryType: $this->deliveryType,
            paymentMethod: $this->paymentMethod,
            paymentStatus: $this->paymentStatus,
            taxAmount: $this->taxAmount,
            deliveryFee: $this->deliveryFee,
            discountAmount: $this->discountAmount,
            couponCode: $this->couponCode,
            cancellationReason: $reason,
            cancelledAt: new DateTimeImmutable,
            cancelledById: $cancelledByUserId,
            paidAt: $this->paidAt,
            items: $this->items
        );
    }

    public function wasPaid(): bool
    {
        return $this->paymentStatus === 'paid' && $this->paidAt !== null;
    }
}
