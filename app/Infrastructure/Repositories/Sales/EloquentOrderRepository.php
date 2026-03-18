<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Domain\Sales\Entities\OrderEntity;
use App\Domain\Sales\Exceptions\OrderNotFoundException;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderItem;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Infrastructure adapter for order persistence.
 */
final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int|string $id): ?OrderEntity
    {
        $model = Order::query()->with(['items', 'tenant'])->find($id);

        return $model instanceof Order ? $this->toEntity($model) : null;
    }

    public function save(OrderEntity $order): OrderEntity
    {
        $model = $order->id !== null
            ? Order::query()->find($order->id) ?? throw OrderNotFoundException::withId($order->id)
            : new Order;

        foreach ($this->attributesForPersistence($order) as $attribute => $value) {
            $model->setAttribute($attribute, $value);
        }

        $model->save();

        if ($order->items !== []) {
            $model->items()->delete();

            foreach ($order->items as $itemData) {
                $lineTotal = (float) ($itemData['line_total']
                    ?? (($itemData['unit_price'] ?? $itemData['price'] ?? 0) * ($itemData['quantity'] ?? 1)));

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

        /** @var Order $fresh */
        $fresh = $model->fresh(['items', 'tenant']) ?? $model->load(['items', 'tenant']);

        return $this->toEntity($fresh);
    }

    public function generateOrderNumber(int|string $tenantId): string
    {
        return Order::generateOrderNumber($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesForPersistence(OrderEntity $order): array
    {
        return [
            'tenant_id' => $order->tenantId,
            'location_id' => $order->locationId,
            'user_id' => $order->customerId,
            'order_number' => $order->orderNumber,
            'customer_name' => $order->customerName,
            'customer_email' => $order->customerEmail,
            'customer_phone' => $order->customerPhone,
            'delivery_address' => $order->customerAddress,
            'customer_notes' => $order->customerNotes,
            'subtotal' => $order->subtotal,
            'total' => $order->total,
            'status' => $order->status,
            'payment_method' => $order->paymentMethod,
            'payment_status' => $order->paymentStatus,
            'delivery_fee' => $order->deliveryFee,
            'discount' => $order->discountAmount,
            'coupon_code' => $order->couponCode,
            'cancellation_reason' => $order->cancellationReason,
            'cancelled_at' => $order->cancelledAt,
            'paid_at' => $order->paidAt,
        ];
    }

    private function toEntity(Order $model): OrderEntity
    {
        /** @var Collection<int, OrderItem> $items */
        $items = $model->getRelationValue('items') instanceof Collection
            ? $model->getRelationValue('items')
            : collect();

        return new OrderEntity(
            id: $model->getKey() !== null ? (int) $model->getKey() : null,
            tenantId: $this->stringAttribute($model, 'tenant_id'),
            locationId: $this->nullableStringAttribute($model, 'location_id'),
            customerId: $this->stringAttribute($model, 'user_id'),
            orderNumber: $this->stringAttribute($model, 'order_number'),
            customerName: $this->stringAttribute($model, 'customer_name'),
            customerEmail: $this->stringAttribute($model, 'customer_email'),
            currency: 'HNL',
            subtotal: $this->floatAttribute($model, 'subtotal'),
            total: $this->floatAttribute($model, 'total'),
            status: $this->stringAttribute($model, 'status'),
            createdAt: $this->immutableDate($model->getAttribute('created_at')),
            customerPhone: $this->nullableStringAttribute($model, 'customer_phone'),
            customerAddress: $this->nullableStringAttribute($model, 'delivery_address'),
            customerNotes: $this->nullableStringAttribute($model, 'customer_notes'),
            deliveryType: 'pickup',
            paymentMethod: $this->stringAttribute($model, 'payment_method', 'pending'),
            paymentStatus: $this->stringAttribute($model, 'payment_status', 'pending'),
            taxAmount: 0.0,
            deliveryFee: $this->floatAttribute($model, 'delivery_fee'),
            discountAmount: $this->floatAttribute($model, 'discount'),
            couponCode: $this->nullableStringAttribute($model, 'coupon_code'),
            cancellationReason: $this->nullableStringAttribute($model, 'cancellation_reason'),
            cancelledAt: $this->nullableImmutableDate($model->getAttribute('cancelled_at')),
            cancelledById: null,
            paidAt: $this->nullableImmutableDate($model->getAttribute('paid_at')),
            items: $items->map(
                /**
                 * @return array<string, mixed>
                 */
                static fn (OrderItem $item): array => [
                    'id' => $item->getKey(),
                    'product_id' => $item->getAttribute('product_id'),
                    'name' => (string) ($item->getAttribute('product_name') ?? ''),
                    'unit_price' => (float) ($item->getAttribute('unit_price') ?? $item->getAttribute('product_price') ?? 0),
                    'quantity' => (int) ($item->getAttribute('quantity') ?? 1),
                    'line_total' => (float) ($item->getAttribute('line_total') ?? $item->getAttribute('total_price') ?? 0),
                    'options' => is_array($item->getAttribute('selected_options')) ? $item->getAttribute('selected_options') : [],
                    'notes' => $item->getAttribute('notes'),
                ]
            )->all(),
        );
    }

    private function stringAttribute(Order $model, string $attribute, string $default = ''): string
    {
        return (string) ($model->getAttribute($attribute) ?? $default);
    }

    private function nullableStringAttribute(Order $model, string $attribute): ?string
    {
        $value = $model->getAttribute($attribute);

        return $value !== null ? (string) $value : null;
    }

    private function floatAttribute(Order $model, string $attribute): float
    {
        return (float) ($model->getAttribute($attribute) ?? 0);
    }

    private function immutableDate(mixed $value): DateTimeImmutable
    {
        return $value instanceof \DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable('now');
    }

    private function nullableImmutableDate(mixed $value): ?DateTimeImmutable
    {
        return $value instanceof \DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : null;
    }
}
