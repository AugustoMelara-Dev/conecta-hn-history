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
        // UUID STRING: Use toString() for tenant context
        return [
            new \App\Domain\Shared\Middleware\TenantQueueMiddleware($event->tenantId()->toString()),
        ];
    }

    /**
     * Handle the LowStockThresholdReached event with atomic locking.
     */
    public function handle(LowStockThresholdReached $event): void
    {
        $productId = $event->aggregateId();

        // Fresh Fetch: Re-hydrate product within tenant context
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

        Log::info("🚨 LOW STOCK ALERT: {$product->name}", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'current_stock' => $newStock,
            'threshold' => $threshold,
            'tenant_id' => $event->tenantId()->toString(),
            'owner_email' => $owner->email,
        ]);

        Log::debug('LowStockNotification: Alert sent with atomic lock', [
            'product_id' => $product->id,
            'expires_in_seconds' => $secondsUntilMidnight,
        ]);
    }
}
