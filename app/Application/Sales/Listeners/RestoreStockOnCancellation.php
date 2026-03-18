<?php

declare(strict_types=1);

namespace App\Application\Sales\Listeners;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Sales\Events\OrderCancelled;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;

final readonly class RestoreStockOnCancellation
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private OrderRepositoryInterface $orderRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderCancelled $event): void
    {
        // Load order to get items (we need product_id and quantity)
        $order = $this->orderRepository->findById((int) $event->aggregateId());

        if ($order === null) {
            $this->logger->warning('RestoreStockOnCancellation: Order not found', [
                'order_id' => $event->aggregateId(),
            ]);

            return;
        }

        // Restore stock for each item
        foreach ($order->items as $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if (! is_int($productId) || ! is_int($quantity)) {
                continue;
            }

            $this->productRepository->incrementStock(
                $productId,
                $quantity
            );
        }

        $this->logger->info('Stock restored after order cancellation', [
            'order_id' => $event->aggregateId(),
            'items_count' => count($order->items),
        ]);
    }
}
