<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Broadcast\LowStockBroadcast;
use App\Events\Inventory\LowStockThresholdReached;
use Illuminate\Support\Facades\Cache;

/**
 * LowStockListener - Application Layer Bridge for Inventory Alerts
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  APPLICATION LAYER: Reacts to Inventory Events, Orchestrates Alerts    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  FLOW:                                                                  ║
 * ║    InventoryManager::decrementStock()                                   ║
 * ║         │                                                               ║
 * ║         ▼                                                               ║
 * ║    DomainEventBus->publish(LowStockThresholdReached)                   ║
 * ║         │                                                               ║
 * ║         ▼                                                               ║
 * ║    THIS LISTENER                                                        ║
 * ║         │                                                               ║
 * ║         ├──▶ SendAlertEmail (rate-limited: 1 per product per day)      ║
 * ║         ├──▶ LogToAuditTrail                                           ║
 * ║         └──▶ broadcast(LowStockBroadcast) (UI update)                  ║
 * ║                                                                          ║
 * ║  RATE LIMITING:                                                         ║
 * ║    To prevent alert fatigue, emails are rate-limited to 1 per product  ║
 * ║    per day. WebSocket broadcasts are NOT rate-limited.                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LowStockListener
{
    /**
     * Handle the LowStockThresholdReached domain event.
     */
    public function handle(LowStockThresholdReached $event): void
    {
        $cacheKey = "low_stock_alert:{$event->aggregateId()}:".now()->format('Y-m-d');

        // ─────────────────────────────────────────────────────────────────────
        // STEP 1: Rate-Limited Email Notification (max 1 per product per day)
        // ─────────────────────────────────────────────────────────────────────
        if (! Cache::has($cacheKey)) {
            // Mark as notified for today
            Cache::put($cacheKey, true, now()->endOfDay());

            // NOTE: Replace with actual email notification
            // Mail::to($tenantOwner)->send(new LowStockAlert($event));
            logger()->warning('Low stock alert', [
                'product_id' => $event->aggregateId(),
                'product_name' => $event->productName,
                'current_stock' => $event->currentStock,
                'threshold' => $event->threshold,
                'severity' => $event->severity(),
                'tenant_id' => $event->tenantId()->toInt(),
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // STEP 2: Real-Time UI Update (always broadcast, no rate limit)
        // ─────────────────────────────────────────────────────────────────────
        broadcast(LowStockBroadcast::fromDomainData(
            tenantId: $event->tenantId()->toInt(),
            productId: $event->aggregateId(),
            productName: $event->productName,
            productSku: $event->productSku,
            currentStock: $event->currentStock,
            threshold: $event->threshold,
            severity: $event->severity(),
            occurredAt: $event->occurredAt(),
        ));
    }
}
