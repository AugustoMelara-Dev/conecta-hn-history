<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Order\OrderDelivered;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\LoyaltyLedger;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(OrderDelivered $event): array
    {
        return [new TenantQueueMiddleware($event->tenantId()->toString())];
    }

    public function handle(OrderDelivered $event): bool
    {
        $tenantId = $event->tenantId()->toString();
        $order = Order::query()
            ->whereKey($event->aggregateId())
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $order) {
            Log::warning('LoyaltyPoints: Order not found in event tenant', [
                'order_id' => $event->aggregateId(),
                'tenant_id' => $tenantId,
            ]);

            return false;
        }

        $userId = $order->getAttribute('user_id');
        $paymentStatus = (string) $order->getAttribute('payment_status');

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD: Only authenticated orders qualify for points
        // ═══════════════════════════════════════════════════════════════════════
        if ($userId === null) {
            Log::debug('LoyaltyPoints: Skipped - Guest order', ['order_id' => $order->id]);

            return false;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD: Order must be delivered and paid
        // ═══════════════════════════════════════════════════════════════════════
        if ($order->status !== Order::STATUS_DELIVERED || $paymentStatus !== 'paid') {
            Log::debug('LoyaltyPoints: Skipped - Order not delivered/paid', [
                'order_id' => $order->id,
                'status' => $order->status,
                'payment_status' => $paymentStatus,
            ]);

            return false;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD: Tenant must have active loyalty program
        // ═══════════════════════════════════════════════════════════════════════
        $program = LoyaltyProgram::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (! $program) {
            Log::debug('LoyaltyPoints: Skipped - No active program', [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
            ]);

            return false;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // IDEMPOTENCY: Check if points already awarded for this order
        // ═══════════════════════════════════════════════════════════════════════
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

        try {
            DB::transaction(static fn (): bool => DB::table('loyalty_ledger')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'order_id' => $order->id,
                'idempotency_key' => "order:{$order->id}:earn",
                'type' => LoyaltyLedger::TYPE_EARN,
                'amount' => $points,
                'description' => "Puntos por pedido {$order->order_number}",
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]), 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            Log::info('LoyaltyPoints: Skipped - Already awarded', ['order_id' => $order->id]);

            return false;
        }

        Log::info('LoyaltyPoints: Awarded', [
            'order_id' => $order->id,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'points' => $points,
            'expires_at' => $expiresAt?->toDateTimeString(),
        ]);

        return true;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23505'
            || in_array($driverCode, ['1062', '1555', '2067'], true);
    }
}
