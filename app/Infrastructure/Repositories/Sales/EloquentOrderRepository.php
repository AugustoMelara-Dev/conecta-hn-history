<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Domain\Sales\Entities\OrderEntity;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use DateTimeImmutable;

/**
 * EloquentOrderRepository - Infrastructure Adapter for Order Persistence
 *
 * FIXED: Updated method signatures to match OrderRepositoryInterface.
 * - findById() now returns ?OrderEntity
 * - Replaced createWithItems(array) with save(OrderEntity)
 */
final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?OrderEntity
    {
        $model = Order::with(['items', 'tenant'])->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(OrderEntity $order): OrderEntity
    {
        $model = $order->id
            ? Order::find($order->id) ?? throw new \App\Domain\Sales\Exceptions\OrderNotFoundException("Order {$order->id} not found")
            : new Order;

        $model->tenant_id = $order->tenantId;
        $model->location_id = $order->locationId;
        $model->user_id = $order->customerId;
        $model->order_number = $order->orderNumber;
        $model->customer_name = $order->customerName;
        $model->customer_email = $order->customerEmail;
        $model->customer_phone = $order->customerPhone;
        $model->delivery_address = $order->customerAddress;
        $model->customer_notes = $order->customerNotes;
        $model->subtotal = $order->subtotal;
        $model->total = $order->total;
        $model->status = $order->status;
        $model->payment_method = $order->paymentMethod;
        $model->delivery_fee = $order->deliveryFee;
        $model->discount = $order->discountAmount;

        $model->save();

        if (! empty($order->items)) {
            $model->items()->delete();

            foreach ($order->items as $itemData) {
                $lineTotal = $itemData['line_total']
                    ?? (($itemData['unit_price'] ?? $itemData['price'] ?? 0) * ($itemData['quantity'] ?? 1));

                $model->items()->create([
                    'product_id' => $itemData['product_id'] ?? null,
                    'product_name' => $itemData['name'] ?? '',
                    'product_price' => $itemData['unit_price'] ?? ($itemData['price'] ?? 0),
                    'unit_price' => $itemData['unit_price'] ?? ($itemData['price'] ?? 0),
                    'quantity' => $itemData['quantity'] ?? 1,
                    'line_total' => $lineTotal,
                    'total_price' => $lineTotal,
                    'selected_options' => $itemData['validated_options'] ?? ($itemData['options'] ?? []),
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }
        }

        return $this->toEntity($model->load('items', 'tenant'));
    }

    public function generateOrderNumber(int|string $tenantId): string
    {
        return Order::generateOrderNumber($tenantId);
    }

    private function toEntity(Order $model): OrderEntity
    {
        return new OrderEntity(
            id: $model->id,
            tenantId: (string) $model->tenant_id,
            locationId: $model->location_id !== null ? (string) $model->location_id : null,
            customerId: (string) $model->user_id,
            orderNumber: $model->order_number,
            customerName: $model->customer_name,
            customerEmail: $model->customer_email,
            currency: 'HNL',
            subtotal: (float) $model->subtotal,
            total: (float) $model->total,
            status: $model->status,
            createdAt: $model->created_at instanceof \DateTime
                ? DateTimeImmutable::createFromMutable($model->created_at)
                : new DateTimeImmutable($model->created_at ?? 'now'),
            customerPhone: $model->customer_phone,
            customerAddress: $model->delivery_address,
            customerNotes: $model->customer_notes,
            deliveryType: 'pickup',
            paymentMethod: $model->payment_method ?? 'pending',
            taxAmount: 0.0,
            deliveryFee: (float) ($model->delivery_fee ?? 0),
            discountAmount: (float) ($model->discount ?? 0),
            couponCode: $model->coupon_code,
            items: $model->items?->toArray() ?? [],
        );
    }
}
