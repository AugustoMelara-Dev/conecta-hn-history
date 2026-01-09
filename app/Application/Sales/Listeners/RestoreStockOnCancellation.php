<?php

declare(strict_types=1);

namespace App\Application\Sales\Listeners;

use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use App\Events\Order\OrderCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * RestoreStockOnCancellation - Async Listener for Inventory Restoration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  EVENTUAL CONSISTENCY: Runs async after OrderCancelled event.           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Decouples Sales domain from Inventory domain                         ║
 * ║  - Tenant-aware via TenantQueueMiddleware                               ║
 * ║  - Retryable: If it fails, the queue will retry automatically          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RestoreStockOnCancellation implements ShouldQueue
{
    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * Handle the event.
     *
     * @param OrderCancelled $event
     * @return void
     */
    public function handle(OrderCancelled $event): void
    {
        // Load order to get items (we need product_id and quantity)
        $order = \App\Models\Order::with('items')->find($event->aggregateId());
        
        if ($order === null) {
            Log::warning('RestoreStockOnCancellation: Order not found', [
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

        Log::info('Stock restored after order cancellation', [
            'order_id' => $event->aggregateId(),
            'items_count' => $order->items->count(),
        ]);
    }

    /**
     * Middleware for tenant-aware queue processing.
     *
     * @param OrderCancelled $event
     * @return array<int, object>
     */
    public function middleware(OrderCancelled $event): array
    {
        return [
            new TenantQueueMiddleware($event->tenantId()->toInt()),
        ];
    }
}
