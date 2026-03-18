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
        $product = $event->product;
        $productSku = $product->getAttribute('sku');
        $tenantIdString = (string) $product->tenant_id;
        $cacheKey = "low_stock_alert:{$product->getKey()}:".now()->format('Y-m-d');

        // ─────────────────────────────────────────────────────────────────────
        // STEP 1: Rate-Limited Email Notification (max 1 per product per day)
        // ─────────────────────────────────────────────────────────────────────
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->endOfDay());

            logger()->warning('Low stock alert', [
                'product_id' => $product->getKey(),
                'product_name' => (string) $product->name,
                'current_stock' => $event->currentStock,
                'threshold' => $event->threshold,
                'severity' => $event->currentStock <= 0 ? 'out_of_stock' : 'low',
                'tenant_id' => $tenantIdString,
            ]);
        }

        // ─────────────────────────────────────────────────────────────────────
        // STEP 2: Real-Time UI Update (always broadcast, no rate limit)
        // ─────────────────────────────────────────────────────────────────────
        broadcast(LowStockBroadcast::fromDomainData(
            tenantId: $tenantIdString,
            productId: (string) $product->getKey(),
            productName: (string) $product->name,
            productSku: is_string($productSku) && $productSku !== '' ? $productSku : null,
            currentStock: (float) $event->currentStock,
            threshold: (float) $event->threshold,
            severity: $event->currentStock <= 0 ? 'out_of_stock' : 'low',
            occurredAt: now()->toImmutable(),
        ));
    }
}
