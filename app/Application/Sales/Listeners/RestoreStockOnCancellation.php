<?php

declare(strict_types=1);

namespace App\Application\Sales\Listeners;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use App\Events\Order\OrderCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * RestoreStockOnCancellation - Async Listener for Inventory Restoration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  EVENTUAL CONSISTENCY: Runs async after OrderCancelled event.           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Decouples Sales domain from Inventory domain                         ║
 * ║  - Tenant-aware via TenantQueueMiddleware                               ║
 * ║  - Retryable: If it fails, the queue will retry automatically          ║
 * ║  - FRAMEWORK AGNOSTIC: Uses LoggerInterface instead of Log facade      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RestoreStockOnCancellation implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderCancelled $event): void
    {
        // Load order to get items (we need product_id and quantity)
        $order = $this->orderRepository->findById($event->aggregateId());

        if ($order === null) {
            $this->logger->warning('RestoreStockOnCancellation: Order not found', [
                'order_id' => $event->aggregateId(),
            ]);

            return;
        }

        // Restore stock for each item
        foreach ($order->items as $item) {
            $this->productRepository->incrementStock(
                $item->product_id,
                $item->quantity
            );
        }

        $this->logger->info('Stock restored after order cancellation', [
            'order_id' => $event->aggregateId(),
            'items_count' => $order->items->count(),
        ]);
    }

    /**
     * Middleware for tenant-aware queue processing.
     *
     * @return array<int, object>
     */
    public function middleware(OrderCancelled $event): array
    {
        // UUID STRING: Use toString() for tenant context
        return [
            new TenantQueueMiddleware($event->tenantId()->toString()),
        ];
    }
}
