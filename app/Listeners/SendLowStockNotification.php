<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Inventory\LowStockThresholdReached;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
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
            new TenantQueueMiddleware($event->tenantId()->toString()),
        ];
    }

    /**
     * Handle the LowStockThresholdReached event with atomic locking.
     */
    public function handle(LowStockThresholdReached $event): void
    {
        $productId = (string) $event->productId;
        $tenantIdString = (string) $event->tenantKey;

        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY: EXPLICIT TENANT-SCOPED FETCH (NO GLOBAL SCOPE RELIANCE)
        // ═══════════════════════════════════════════════════════════════════════
        // CRITICAL: We NEVER use Product::find() in background jobs.
        // Background workers may not have the global tenant scope booted.
        // We always enforce tenant isolation at the SQL WHERE clause level.
        $product = Product::query()
            ->where('id', $productId)
            ->where('tenant_id', $tenantIdString) // THE IMMUTABLE FIREWALL
            ->first();

        if (! $product) {
            // Check if product exists but belongs to different tenant (security probe)
            $existsElsewhere = Product::where('id', $productId)->exists();

            if ($existsElsewhere) {
                // SECURITY ALERT: Cross-tenant access attempt blocked
                Log::channel('security')->critical('🚨 Cross-Tenant Access Attempt BLOCKED', [
                    'event' => 'LowStockNotification',
                    'resource_type' => 'Product',
                    'resource_id' => $productId,
                    'requested_tenant_context' => $tenantIdString,
                    'severity' => 'CRITICAL',
                    'action' => 'BLOCKED',
                ]);
            } else {
                Log::warning("LowStockNotification: Product #{$productId} not found (could be deleted)", [
                    'tenant_id' => $tenantIdString,
                ]);
            }

            return; // Graceful termination - do not retry malicious jobs
        }

        $newStock = $event->currentStock;
        $threshold = $event->threshold;

        // ═══════════════════════════════════════════════════════════════════════
        // ATOMIC LOCK: Prevents race condition duplicates
        // ═══════════════════════════════════════════════════════════════════════
        $cacheKey = "low_stock_alert_sent_{$tenantIdString}_{$productId}_".now()->format('Y-m-d');
        $secondsUntilMidnight = max(1, (int) ceil(now()->endOfDay()->diffInSeconds(now())));

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
        /** @var Tenant|null $tenant */
        $tenant = $product->tenant;

        /** @var User|null $owner */
        $owner = $tenant?->user()->first();

        if (! $owner) {
            Log::warning("LowStockNotification: No owner found for product #{$productId}");

            return;
        }

        Log::info("🚨 LOW STOCK ALERT: {$product->name}", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'current_stock' => $newStock,
            'threshold' => $threshold,
            'tenant_id' => $tenantIdString,
            'owner_email' => $owner->email,
        ]);

        Log::debug('LowStockNotification: Alert sent with atomic lock', [
            'product_id' => $product->id,
            'expires_in_seconds' => $secondsUntilMidnight,
        ]);
    }
}
