<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Domain\Sales\Entities\OrderEntity;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderItem;
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

    public function save(OrderEntity $order): void
    {
        $model = $order->id 
            ? Order::findOrFail($order->id) 
            : new Order();

        $model->tenant_id = $order->tenantId;
        $model->customer_id = $order->customerId;
        $model->order_number = $order->orderNumber;
        $model->customer_name = $order->customerName;
        $model->customer_email = $order->customerEmail;
        $model->customer_phone = $order->customerPhone;
        $model->customer_address = $order->customerAddress;
        $model->customer_notes = $order->customerNotes;
        $model->currency = $order->currency;
        $model->subtotal = $order->subtotal;
        $model->total = $order->total;
        $model->status = $order->status;
        $model->delivery_type = $order->deliveryType;
        $model->payment_method = $order->paymentMethod;
        $model->tax_amount = $order->taxAmount;
        $model->delivery_fee = $order->deliveryFee;
        $model->discount_amount = $order->discountAmount;
        $model->coupon_code = $order->couponCode;
        
        $model->save();
        
        // Handle items if provided
        if (!empty($order->items)) {
            $model->items()->delete();
            
            foreach ($order->items as $itemData) {
                $model->items()->create([
                    'product_id' => $itemData['product_id'] ?? null,
                    'name' => $itemData['name'] ?? '',
                    'price' => $itemData['price'] ?? 0,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'options' => $itemData['options'] ?? [],
                    'notes' => $itemData['notes'] ?? null,
                    'line_total' => $itemData['line_total'] ?? 0,
                ]);
            }
        }
        
        $order->setId($model->id);
    }

    public function generateOrderNumber(int $tenantId): string
    {
        return Order::generateOrderNumber($tenantId);
    }

    private function toEntity(Order $model): OrderEntity
    {
        return new OrderEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            customerId: $model->customer_id,
            orderNumber: $model->order_number,
            customerName: $model->customer_name,
            customerEmail: $model->customer_email,
            currency: $model->currency,
            subtotal: (float) $model->subtotal,
            total: (float) $model->total,
            status: $model->status,
            createdAt: $model->created_at instanceof \DateTime 
                ? DateTimeImmutable::createFromMutable($model->created_at) 
                : new DateTimeImmutable($model->created_at ?? 'now'),
            customerPhone: $model->customer_phone,
            customerAddress: $model->customer_address,
            customerNotes: $model->customer_notes,
            deliveryType: $model->delivery_type ?? 'pickup',
            paymentMethod: $model->payment_method ?? 'pending',
            taxAmount: (float) ($model->tax_amount ?? 0),
            deliveryFee: (float) ($model->delivery_fee ?? 0),
            discountAmount: (float) ($model->discount_amount ?? 0),
            couponCode: $model->coupon_code,
            items: $model->items?->toArray() ?? [],
        );
    }
}
