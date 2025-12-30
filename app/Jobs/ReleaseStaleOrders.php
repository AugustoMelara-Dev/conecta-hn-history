<?php

namespace App\Jobs;

use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReleaseStaleOrders - Context-Aware Inventory Recovery
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MARKET-ADAPTED GARBAGE COLLECTOR: Honduras Payment Workflow Support    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * BUSINESS CONTEXT (Honduras Market):
 * - Low credit card penetration
 * - Bank transfers require: exit app → open bank → register beneficiary →
 *   transfer → screenshot → WhatsApp verification (often > 30 minutes)
 * - Cash orders tied to delivery logistics (24h cycles)
 *
 * TIMEOUT STRATEGY (Payment-Method-Aware):
 * - Bank Transfer: 4 HOURS (manual verification latency)
 * - Cash on Delivery: 24 HOURS (logistics cycle)
 * - Card/Digital: 30 MINUTES (abandoned cart detection)
 *
 * ARCHITECTURAL PATTERNS:
 * - Memory-Safe: chunkById(100) prevents OOM
 * - Race-Condition-Free: lockForUpdate() + status re-validation
 * - DRY: Delegates to OrderObserver for inventory restoration
 */
class ReleaseStaleOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Batch size for memory-efficient processing.
     */
    /**
     * Batch size for memory-efficient processing.
     */
    protected const CHUNK_SIZE = 100;

    /**
     * Tenant Context Marker for Static Analysis.
     * Use when dispatching for specific tenant if needed.
     */
    public $tenant_id;

    /**
     * Execute the job with context-aware timeout logic.
     */
    public function handle(): void
    {
        $processedCount = 0;
        $cancelledCount = 0;

        Log::info('ReleaseStaleOrders: Starting context-aware batch processing.');

        // ═══════════════════════════════════════════════════════════════════════
        // CONTEXT-AWARE TIMEOUT QUERY
        // ═══════════════════════════════════════════════════════════════════════
        // Different payment methods get different grace periods:
        // - Bank Transfer: 4 hours (manual verification workflow)
        // - Cash on Delivery: 24 hours (logistics cycle)
        // - Card/Pending: 30 minutes (abandoned cart)
        //
        // SQL Logic: (payment_method = 'bank_transfer' AND created_at < 4h ago)
        //         OR (payment_method = 'cash' AND created_at < 24h ago)
        //         OR (payment_method IN ('card', 'pending') AND created_at < 30min ago)
        // ═══════════════════════════════════════════════════════════════════════

        $bankTransferThreshold = now()->subHours(4);
        $cashThreshold = now()->subHours(24);
        $digitalThreshold = now()->subMinutes(30);

        Order::where('status', Order::STATUS_PENDING)
            ->where(function ($query) use ($bankTransferThreshold, $cashThreshold, $digitalThreshold) {
                // Bank Transfer: 4 hour window
                $query->where(function ($q) use ($bankTransferThreshold) {
                    $q->where('payment_method', PaymentMethod::BankTransfer->value)
                        ->where('created_at', '<', $bankTransferThreshold);
                })
                // Cash: 24 hour window
                    ->orWhere(function ($q) use ($cashThreshold) {
                        $q->where('payment_method', PaymentMethod::Cash->value)
                            ->where('created_at', '<', $cashThreshold);
                    })
                // Card/Pending/Other: 30 minute window
                    ->orWhere(function ($q) use ($digitalThreshold) {
                        $q->whereIn('payment_method', [
                            PaymentMethod::Card->value,
                            PaymentMethod::Pending->value,
                        ])
                            ->where('created_at', '<', $digitalThreshold);
                    })
                // Fallback for null/unknown payment methods
                    ->orWhere(function ($q) use ($digitalThreshold) {
                        $q->whereNull('payment_method')
                            ->where('created_at', '<', $digitalThreshold);
                    });
            })
            ->chunkById(self::CHUNK_SIZE, function ($orders) use (&$processedCount, &$cancelledCount) {
                foreach ($orders as $order) {
                    $cancelled = $this->releaseOrderWithLock($order->id);
                    $processedCount++;
                    if ($cancelled) {
                        $cancelledCount++;
                    }
                }
            });

        Log::info('ReleaseStaleOrders: Completed.', [
            'processed' => $processedCount,
            'cancelled' => $cancelledCount,
            'thresholds' => [
                'bank_transfer' => '4 hours',
                'cash' => '24 hours',
                'digital' => '30 minutes',
            ],
        ]);
    }

    /**
     * Release a single stale order with pessimistic locking.
     *
     * CRITICAL: Re-fetches order INSIDE transaction with lockForUpdate()
     * to prevent double-refund race conditions.
     *
     * @param  int  $orderId  The order ID to process (NOT the model instance)
     * @return bool True if order was cancelled, false if skipped
     */
    protected function releaseOrderWithLock(int $orderId): bool
    {
        try {
            return DB::transaction(function () use ($orderId) {
                // ═══════════════════════════════════════════════════════════════
                // CRITICAL: Fresh fetch with exclusive lock INSIDE transaction
                // ═══════════════════════════════════════════════════════════════
                $freshOrder = Order::lockForUpdate()->find($orderId);

                if (! $freshOrder) {
                    Log::warning("ReleaseStaleOrders: Order #{$orderId} not found (deleted?)");

                    return false;
                }

                // ═══════════════════════════════════════════════════════════════
                // RE-VALIDATE CONDITION UNDER EXCLUSIVE LOCK
                // ═══════════════════════════════════════════════════════════════
                if ($freshOrder->status !== Order::STATUS_PENDING) {
                    Log::debug("ReleaseStaleOrders: Order #{$orderId} already processed", [
                        'current_status' => $freshOrder->status,
                    ]);

                    return false;
                }

                // ═══════════════════════════════════════════════════════════════
                // CANCEL THE ORDER - TRIGGERS OBSERVER FOR RESTOCK
                // ═══════════════════════════════════════════════════════════════
                $paymentMethod = $freshOrder->payment_method instanceof PaymentMethod
                    ? $freshOrder->payment_method->value
                    : $freshOrder->payment_method;

                $freshOrder->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancel_reason' => 'expired',
                ]);

                Log::info("Order #{$freshOrder->order_number} expired (payment: {$paymentMethod})", [
                    'order_id' => $freshOrder->id,
                    'tenant_id' => $freshOrder->tenant_id,
                    'payment_method' => $paymentMethod,
                    'age_minutes' => now()->diffInMinutes($freshOrder->created_at),
                ]);

                return true;
            });

        } catch (\Exception $e) {
            Log::error("Failed to release stale order #{$orderId}: {$e->getMessage()}", [
                'order_id' => $orderId,
                'exception' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
