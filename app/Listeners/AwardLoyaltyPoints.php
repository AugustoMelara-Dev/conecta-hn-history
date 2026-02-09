<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoyaltyLedger;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Award Loyalty Points Listener
 * 
 * Automatically awards loyalty points when an order is completed.
 * Triggered by Order status transition to 'delivered' with payment confirmed.
 * 
 * INVARIANTS:
 * - Only awards points for orders with user_id (authenticated users)
 * - Only awards if tenant has active loyalty program
 * - Points calculated as: floor(order.total / program.earn_ratio)
 * - Points expire after program.expiry_days
 * - Idempotent: Won't award twice for same order
 */
class AwardLoyaltyPoints implements ShouldQueue
{
    /**
     * Award loyalty points for a completed order.
     * 
     * @param Order $order The completed order
     * @return bool True if points were awarded
     */
    public function handle(Order $order): bool
    {
        // ═══════════════════════════════════════════════════════════════════════
        // GUARD: Only authenticated orders qualify for points
        // ═══════════════════════════════════════════════════════════════════════
        if ($order->user_id === null) {
            Log::debug('LoyaltyPoints: Skipped - Guest order', ['order_id' => $order->id]);
            return false;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD: Order must be delivered and paid
        // ═══════════════════════════════════════════════════════════════════════
        if ($order->status !== Order::STATUS_DELIVERED || $order->payment_status !== 'paid') {
            Log::debug('LoyaltyPoints: Skipped - Order not delivered/paid', [
                'order_id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
            ]);
            return false;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD: Tenant must have active loyalty program
        // ═══════════════════════════════════════════════════════════════════════
        $program = LoyaltyProgram::where('tenant_id', $order->tenant_id)
            ->where('is_active', true)
            ->first();

        if (!$program) {
            Log::debug('LoyaltyPoints: Skipped - No active program', [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
            ]);
            return false;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // IDEMPOTENCY: Check if points already awarded for this order
        // ═══════════════════════════════════════════════════════════════════════
        $existingEntry = LoyaltyLedger::where('order_id', $order->id)
            ->where('type', LoyaltyLedger::TYPE_EARN)
            ->exists();

        if ($existingEntry) {
            Log::info('LoyaltyPoints: Skipped - Already awarded', ['order_id' => $order->id]);
            return false;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // CALCULATE: Points based on earn ratio
        // ═══════════════════════════════════════════════════════════════════════
        $points = $program->calculatePoints($order->total);

        if ($points <= 0) {
            Log::debug('LoyaltyPoints: Skipped - Zero points', [
                'order_id' => $order->id,
                'total' => $order->total,
                'earn_ratio' => $program->earn_ratio,
            ]);
            return false;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // AWARD: Create ledger entry
        // ═══════════════════════════════════════════════════════════════════════
        $expiresAt = $program->expiry_days > 0 
            ? now()->addDays($program->expiry_days) 
            : null;

        LoyaltyLedger::create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'type' => LoyaltyLedger::TYPE_EARN,
            'amount' => $points,
            'description' => "Puntos por pedido {$order->order_number}",
            'expires_at' => $expiresAt,
        ]);

        Log::info('LoyaltyPoints: Awarded', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'tenant_id' => $order->tenant_id,
            'points' => $points,
            'expires_at' => $expiresAt?->toDateTimeString(),
        ]);

        return true;
    }
}
