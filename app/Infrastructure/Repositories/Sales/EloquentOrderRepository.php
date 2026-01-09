<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * EloquentOrderRepository - Infrastructure Adapter for Order Persistence
 *
 * Encapsulates all Eloquent ORM calls for Order aggregate.
 */
final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?object
    {
        return Order::with(['items', 'tenant'])->find($id);
    }

    public function createWithItems(array $orderAttributes, array $items): object
    {
        $order = Order::create($orderAttributes);
        
        $orderItems = [];
        foreach ($items as $itemData) {
            if ($itemData['is_custom'] ?? false) {
                $orderItem = OrderItem::fromCartItem([
                    'name' => $itemData['name'],
                    'price' => $itemData['unit_price'],
                    'quantity' => $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            } else {
                $orderItem = new OrderItem([
                    'product_id' => $itemData['product_id'] ?? null,
                    'name' => $itemData['name'],
                    'price' => $itemData['unit_price'],
                    'quantity' => $itemData['quantity'],
                    'options' => $itemData['validated_options'] ?? [],
                    'notes' => $itemData['notes'] ?? null,
                ]);
                $orderItem->line_total = $itemData['line_total'] ?? 0;
            }
            $orderItems[] = $orderItem;
        }
        
        $order->items()->saveMany($orderItems);
        $order->load(['items', 'tenant']);
        
        return $order;
    }

    public function generateOrderNumber(int $tenantId): string
    {
        return Order::generateOrderNumber($tenantId);
    }

    public function linkCoupon(int $orderId, int $couponId): void
    {
        // This is handled by CouponValidatorService
    }
}
