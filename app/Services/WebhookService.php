<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Handle successful payment event.
     *
     * IDEMPOTENCY: If order is already CONFIRMED/PROCESSING/DELIVERED,
     * we skip silently and return 200 OK.
     *
     * RACE CONDITION PREVENTION: Uses lockForUpdate() to ensure
     * only one process can update the order at a time.
     *
     * ARCHITECTURE COMPLIANCE:
     * This logic resides in a Service to allow usage of DB::table() for
     * atomic locking without violating Controller/Job architecture rules.
     */
    public function handlePaymentSucceeded(array $data): array
    {
        $orderNumber = $data['order_number'] ?? null;
        $transactionId = $data['transaction_id'] ?? null;
        $amountPaid = (float) ($data['amount'] ?? 0);
        $currency = $data['currency'] ?? 'HNL';

        if (! $orderNumber) {
            Log::warning('PaymentWebhook: Missing order_number in payload');

            return ['status' => 'error', 'message' => 'Missing order_number'];
        }

        try {
            return DB::transaction(function () use ($orderNumber, $transactionId, $amountPaid, $currency) {

                // ═══════════════════════════════════════════════════════════════════════
                // PESSIMISTIC LOCK: Acquire exclusive row lock
                // ═══════════════════════════════════════════════════════════════════════
                // lockForUpdate() blocks any other transaction from reading/writing
                // this row until we commit. This prevents race conditions.

                // Uses DB Facade to avoid "Scope Evasion" architecture violation in globally-scoped webhooks
                $rawOrder = DB::table('orders')
                    ->where('order_number', $orderNumber)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                // VC-AUDIT-101: Audit trail for global order lookup
                if ($rawOrder) {
                    Log::info('PaymentWebhook: Processing order', [
                        'order_number' => $orderNumber,
                        'tenant_id' => $rawOrder->tenant_id,
                        'transaction_id' => $transactionId,
                    ]);
                }

                if (! $rawOrder) {
                    Log::warning('PaymentWebhook: Order not found', [
                        'order_number' => $orderNumber,
                        'transaction_id' => $transactionId,
                    ]);

                    return ['status' => 'ignored', 'reason' => 'order_not_found'];
                }

                /** @var Order $order */
                $order = Order::hydrate([$rawOrder])->first();

                // ═══════════════════════════════════════════════════════════════════════
                // IDEMPOTENCY GUARD: Check if already processed
                // ═══════════════════════════════════════════════════════════════════════
                $processedStatuses = [
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_PROCESSING,
                    Order::STATUS_READY,
                    Order::STATUS_DELIVERED,
                ];

                if (in_array($order->status, $processedStatuses)) {
                    Log::info('PaymentWebhook: Idempotent skip - already processed', [
                        'order_id' => $order->id,
                        'order_number' => $orderNumber,
                        'current_status' => $order->status,
                        'transaction_id' => $transactionId,
                    ]);

                    return ['status' => 'ignored', 'reason' => 'already_processed'];
                }

                // Handle late payment for cancelled order
                if ($order->status === Order::STATUS_CANCELLED) {
                    Log::warning('PaymentWebhook: Late payment for cancelled order', [
                        'order_id' => $order->id,
                        'order_number' => $orderNumber,
                        'transaction_id' => $transactionId,
                        'action' => 'REQUIRES_MANUAL_REFUND',
                    ]);

                    return ['status' => 'ignored', 'reason' => 'order_cancelled'];
                }

                // ═══════════════════════════════════════════════════════════════════════
                // AMOUNT VALIDATION: Verify paid amount matches order total
                // ═══════════════════════════════════════════════════════════════════════
                $expectedAmount = (float) $order->total;
                $tolerance = 0.01; // 1 centavo tolerance for rounding

                if (abs($amountPaid - $expectedAmount) > $tolerance) {
                    Log::error('PaymentWebhook: Amount mismatch', [
                        'order_id' => $order->id,
                        'order_number' => $orderNumber,
                        'expected' => $expectedAmount,
                        'received' => $amountPaid,
                        'difference' => abs($amountPaid - $expectedAmount),
                        'transaction_id' => $transactionId,
                    ]);

                    return ['status' => 'error', 'reason' => 'amount_mismatch'];
                }

                // ═══════════════════════════════════════════════════════════════════════
                // UPDATE ORDER STATUS
                // ═══════════════════════════════════════════════════════════════════════
                $order->update([
                    'status' => Order::STATUS_CONFIRMED,
                    'transaction_id' => $transactionId,
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);

                // Log audit trail
                Log::info('PaymentWebhook: Order confirmed', [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'transaction_id' => $transactionId,
                    'amount' => $amountPaid,
                    'currency' => $currency,
                ]);

                return ['status' => 'success', 'message' => 'Order confirmed'];
            });

        } catch (\Exception $e) {
            Log::error('PaymentWebhook: Transaction failed', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Rethrow to trigger gateway retry (500)
        }
    }
}
