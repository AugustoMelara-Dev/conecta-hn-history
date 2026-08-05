<?php

declare(strict_types=1);

namespace App\Application\Sales\Listeners;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Events\Order\OrderCancelled;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Order;
use App\Services\InventoryManager;
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
        private readonly InventoryManager $inventory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderCancelled $event): void
    {
        // Load order to get items (we need product_id and quantity)
        // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        $order = Order::withoutGlobalScopes()
            ->with('items')
            ->whereKey($event->aggregateId())
            ->where('tenant_id', $event->tenantId()->toString())
            ->first();

        if ($order === null) {
            $this->logger->warning('RestoreStockOnCancellation: Order not found', [
                'order_id' => $event->aggregateId(),
            ]);

            return;
        }

        $this->inventory->restock($order);

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
