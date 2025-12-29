<?php

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
class SendLowStockNotification implements ShouldQueue
{
    /**
     * Handle the LowStockThresholdReached event with atomic locking.
     */
    public function handle(LowStockThresholdReached $event): void
    {
        $product = $event->product;
        $newStock = $event->newStockQuantity;
        $threshold = $event->threshold;

        // ═══════════════════════════════════════════════════════════════════════
        // ATOMIC LOCK: Prevents race condition duplicates
        // ═══════════════════════════════════════════════════════════════════════
        // Cache::add() is atomic: write-if-not-exists in a single operation.
        // If key exists → returns false (another process already sent alert today)
        // If key doesn't exist → writes and returns true (we have the lock)
        $cacheKey = "low_stock_alert_sent_{$product->id}_".now()->format('Y-m-d');
        $secondsUntilMidnight = now()->endOfDay()->diffInSeconds(now());

        // Try to acquire atomic lock - ABORT if already sent today
        if (! Cache::add($cacheKey, true, $secondsUntilMidnight)) {
            Log::debug('LowStockNotification: Skipped (already sent today via atomic check)', [
                'product_id' => $product->id,
            ]);

            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // LOCK ACQUIRED: Proceed with notification
        // ═══════════════════════════════════════════════════════════════════════
        $tenant = $product->tenant;

        if (! $tenant || ! $tenant->user) {
            Log::warning("LowStockNotification: No owner found for product #{$product->id}");

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
