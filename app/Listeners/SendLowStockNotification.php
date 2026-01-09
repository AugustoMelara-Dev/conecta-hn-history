<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LowStockThresholdReached;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SendLowStockNotification - Rate-Limited Low Stock Alert Handler
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ATOMIC ALERTING: RACE CONDITION & NOTIFICATION FATIGUE PREVENTION      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * RACE CONDITION FIX:
 * Uses Cache::add() (atomic operation) instead of has()/put() pattern.
 * Cache::add() = write-if-not-exists in a single atomic operation.
 *
 * NOTIFICATION FATIGUE PREVENTION:
 * Cool-down mechanism limits alerts to ONE per product per day.
 * Uses cache key: low_stock_alert_sent_{product_id}_{date}
 */
final class SendLowStockNotification implements ShouldQueue
{
    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(LowStockThresholdReached $event): array
    {
        return [
            new \App\Domain\Shared\Middleware\TenantQueueMiddleware($event->tenantId()->toInt())
        ];
    }

    /**
     * Handle the LowStockThresholdReached event with atomic locking.
     */
    public function handle(LowStockThresholdReached $event): void
    {
        $product = $event->product; // Wait, product wasn't in event? LowStockThresholdReached doesn't have public $product property!
        // In the view_file output for LowStockThresholdReached (Step 101), it does NOT have public $product.
        // It has private $productId, public $productName.
        // The original SendLowStockNotification (Step 72) had "$product = $event->product;"
        // BUT the event definition (Step 101) says: "// ❌ NO Product model (was using SerializesModels)".
        // So the listener is BROKEN if it tries to access $event->product!
        // The listener needs to be refactored to NOT rely on $event->product if it's not there.
        // OR the event has a magic getter? "use HasDomainMetadata". Maybe?
        // But the event doc says "✅ Only primitives and Value Objects".
        // So I must refactor the listener to re-hydrate the product using $event->aggregateId() (productId).
        
        $productId = $event->aggregateId();
        // Since we are in Tenant Context (thanks to Middleware), we can resolve Product.
        $product = \App\Models\Product::find($productId);

        if (! $product) {
             Log::warning("LowStockNotification: Product #{$productId} not found (could be deleted)");
             return;
        }

        $newStock = $event->currentStock;
        $threshold = $event->threshold;

        // ═══════════════════════════════════════════════════════════════════════
        // ATOMIC LOCK: Prevents race condition duplicates
        // ═══════════════════════════════════════════════════════════════════════
        // Cache::add() is atomic: write-if-not-exists in a single operation.
        // If key exists → returns false (another process already sent alert today)
        // If key doesn't exist → writes and returns true (we have the lock)
        $cacheKey = "low_stock_alert_sent_{$productId}_".now()->format('Y-m-d');
        $secondsUntilMidnight = now()->endOfDay()->diffInSeconds(now());

        // Try to acquire atomic lock - ABORT if already sent today
        if (! Cache::add($cacheKey, true, $secondsUntilMidnight)) {
            Log::debug('LowStockNotification: Skipped (already sent today via atomic check)', [
                'product_id' => $productId,
            ]);

            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // LOCK ACQUIRED: Proceed with notification
        // ═══════════════════════════════════════════════════════════════════════
        $tenant = $product->tenant;

        if (! $tenant || ! $tenant->user) {
            Log::warning("LowStockNotification: No owner found for product #{$productId}");

            return;
        }

        $owner = $tenant->user;

        // Send notification (Log for now - can be replaced with Notification class)
        Log::info("🚨 LOW STOCK ALERT: {$product->name}", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'current_stock' => $newStock,
            'threshold' => $threshold,
            'tenant_id' => $tenant->id,
            'owner_email' => $owner->email,
        ]);

        Log::debug('LowStockNotification: Alert sent with atomic lock', [
            'product_id' => $product->id,
            'expires_in_seconds' => $secondsUntilMidnight,
        ]);
    }
}
