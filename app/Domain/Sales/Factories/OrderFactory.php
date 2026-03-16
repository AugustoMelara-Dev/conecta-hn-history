<?php

declare(strict_types=1);

namespace App\Domain\Sales\Factories;

use App\Domain\Sales\Entities\OrderEntity;
use DateTimeImmutable;

final readonly class OrderFactory
{
    public function create(
        string $tenantId,
        string $locationId,
        string $customerId,
        string $orderNumber,
        string $customerName,
        string $customerEmail,
        string $currency,
        float $subtotal,
        float $total,
        array $items,
        ?string $customerPhone = null,
        ?string $customerAddress = null,
        ?string $customerNotes = null,
        string $deliveryType = 'pickup',
        string $paymentMethod = 'pending',
        float $discountAmount = 0.0,
        ?string $couponCode = null,
    ): OrderEntity {
        return new OrderEntity(
            id: null,
            tenantId: $tenantId,
            locationId: $locationId,
            customerId: $customerId,
            orderNumber: $orderNumber,
            customerName: $customerName,
            customerEmail: $customerEmail,
            currency: $currency,
            subtotal: $subtotal,
            total: $total,
            status: 'pending',
            createdAt: new DateTimeImmutable,
            customerPhone: $customerPhone,
            customerAddress: $customerAddress,
            customerNotes: $customerNotes,
            deliveryType: $deliveryType,
            paymentMethod: $paymentMethod,
            taxAmount: 0.0,
            deliveryFee: 0.0,
            discountAmount: $discountAmount,
            couponCode: $couponCode,
            items: $items
        );
    }
}
