<?php

namespace App\Http\Controllers\Webhook;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentWebhookController - Financial Reconciliation Layer
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ZERO-TRUST SECURITY: Cryptographic signature verification required     ║
 * ║  IDEMPOTENCY: Same event processed twice has no side effects            ║
 * ║  ACID COMPLIANCE: Pessimistic locking prevents race conditions          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SUPPORTED EVENTS:
 * - payment.succeeded: Mark order as CONFIRMED, record transaction ID
 * - payment.failed: Log failure, order remains PENDING for retry
 * - payment.refunded: Mark order as REFUNDED, trigger inventory restoration
 *
 * SECURITY MODEL:
 * 1. CSRF bypassed (external provider cannot provide tokens)
 * 2. HMAC-SHA256 signature verification (timing-attack safe)
 * 3. Request body hashed with shared secret
 *
 * FAILURE MODES:
 * - Invalid signature → 403 Forbidden (no retry)
 * - Order not found → 200 OK (prevent infinite retries)
 * - Already processed → 200 OK (idempotent)
 * - Server error → 500 (triggers gateway retry)
 */
class PaymentWebhookController extends Controller
{
    /**
     * Webhook secret for signature verification.
     * In production, this comes from .env: PAYMENT_WEBHOOK_SECRET
     */
    protected string $webhookSecret;

    public function __construct()
    {
        $this->webhookSecret = (string) config('services.payment.webhook_secret', 'test_webhook_secret_change_in_production');
    }

    /**
     * Handle incoming payment webhook.
     *
     * @param Request $request The raw webhook request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: ZERO-TRUST SIGNATURE VERIFICATION
        // ═══════════════════════════════════════════════════════════════════════
        // The gatekeeper pattern: verify origin before parsing payload.
        // If signature fails, we reject immediately - no further processing.

        if (! $this->verifySignature($request)) {
            Log::warning('PaymentWebhook: SECURITY ALERT - Invalid signature detected', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature',
            ], 403);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: PARSE PAYLOAD & EXTRACT EVENT DATA
        // ═══════════════════════════════════════════════════════════════════════
        $payload = $request->json()->all();
        $eventType = $payload['event'] ?? null;
        $eventData = $payload['data'] ?? [];

        Log::info('PaymentWebhook: Event received', [
            'event_type' => $eventType,
            'transaction_id' => $eventData['transaction_id'] ?? 'N/A',
        ]);

        // Route to appropriate handler based on event type
        return match ($eventType) {
            'payment.succeeded' => $this->handlePaymentSucceeded($eventData),
            'payment.failed' => $this->handlePaymentFailed($eventData),
            'payment.refunded' => $this->handlePaymentRefunded($eventData),
            default => $this->handleUnknownEvent($eventType),
        };
    }

    /**
     * Verify the webhook signature using HMAC-SHA256.
     *
     * SECURITY: Uses hash_equals() to prevent timing attacks.
     * A timing attack could deduce the secret by measuring response times.
     *
     * @param Request $request
     * @return bool True if signature is valid
     */
    protected function verifySignature(Request $request): bool
    {
        // Get signature from header (provider-specific header name)
        // Common names: X-Signature, X-Webhook-Signature, Stripe-Signature
        $providedSignature = $request->header('X-Webhook-Signature');

        if (empty($providedSignature)) {
            return false;
        }

        // Reconstruct expected signature from raw payload
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        // Timing-attack safe comparison
        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Handle successful payment event.
     *
     * IDEMPOTENCY: If order is already CONFIRMED/PROCESSING/DELIVERED,
     * we skip silently and return 200 OK.
     *
     * RACE CONDITION PREVENTION: Uses lockForUpdate() to ensure
     * only one process can update the order at a time.
     *
     * @param array $data Event payload data
     * @return JsonResponse
     */
    protected function handlePaymentSucceeded(array $data): JsonResponse
    {
        $orderNumber = $data['order_number'] ?? null;
        $transactionId = $data['transaction_id'] ?? null;
        $amountPaid = (float) ($data['amount'] ?? 0);
        $currency = $data['currency'] ?? 'HNL';

        if (! $orderNumber) {
            Log::warning('PaymentWebhook: Missing order_number in payload');
            return response()->json(['status' => 'error', 'message' => 'Missing order_number'], 200);
        }

        try {
            $result = DB::transaction(function () use ($orderNumber, $transactionId, $amountPaid, $currency) {

                // ═══════════════════════════════════════════════════════════════
                // PESSIMISTIC LOCK: Acquire exclusive row lock
                // ═══════════════════════════════════════════════════════════════
                // lockForUpdate() blocks any other transaction from reading/writing
                // this row until we commit. This prevents race conditions.
                $order = Order::withoutGlobalScopes()
                    ->where('order_number', $orderNumber)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    Log::warning('PaymentWebhook: Order not found', [
                        'order_number' => $orderNumber,
                        'transaction_id' => $transactionId,
                    ]);
                    return ['status' => 'ignored', 'reason' => 'order_not_found'];
                }

                // ═══════════════════════════════════════════════════════════════
                // IDEMPOTENCY GUARD: Check if already processed
                // ═══════════════════════════════════════════════════════════════
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

                // ═══════════════════════════════════════════════════════════════
                // AMOUNT VALIDATION: Verify paid amount matches order total
                // ═══════════════════════════════════════════════════════════════
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

                // ═══════════════════════════════════════════════════════════════
                // STATE TRANSITION: Mark as CONFIRMED
                // ═══════════════════════════════════════════════════════════════
                $order->update([
                    'status' => Order::STATUS_CONFIRMED,
                    'payment_transaction_id' => $transactionId,
                    'payment_confirmed_at' => now(),
                ]);

                Log::info('PaymentWebhook: Payment confirmed successfully', [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'amount' => $amountPaid,
                    'currency' => $currency,
                    'transaction_id' => $transactionId,
                ]);

                return ['status' => 'success', 'order_id' => $order->id];
            });

            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('PaymentWebhook: Server error during payment processing', [
                'order_number' => $orderNumber,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 500 to trigger gateway retry
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle failed payment event.
     *
     * Order remains in PENDING status, awaiting retry or cancellation.
     *
     * @param array $data Event payload data
     * @return JsonResponse
     */
    protected function handlePaymentFailed(array $data): JsonResponse
    {
        $orderNumber = $data['order_number'] ?? null;
        $transactionId = $data['transaction_id'] ?? null;
        $failureReason = $data['failure_reason'] ?? 'Unknown';

        Log::warning('PaymentWebhook: Payment failed', [
            'order_number' => $orderNumber,
            'transaction_id' => $transactionId,
            'failure_reason' => $failureReason,
        ]);

        // No state change needed - order stays PENDING
        // The ReleaseStaleOrders job will eventually clean it up

        return response()->json([
            'status' => 'acknowledged',
            'message' => 'Payment failure recorded',
        ], 200);
    }

    /**
     * Handle refund event.
     *
     * Marks order as refunded and triggers inventory restoration.
     *
     * @param array $data Event payload data
     * @return JsonResponse
     */
    protected function handlePaymentRefunded(array $data): JsonResponse
    {
        $orderNumber = $data['order_number'] ?? null;
        $transactionId = $data['transaction_id'] ?? null;
        $refundAmount = (float) ($data['amount'] ?? 0);

        if (! $orderNumber) {
            return response()->json(['status' => 'error', 'message' => 'Missing order_number'], 200);
        }

        try {
            $result = DB::transaction(function () use ($orderNumber, $transactionId, $refundAmount) {
                $order = Order::withoutGlobalScopes()
                    ->where('order_number', $orderNumber)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    return ['status' => 'ignored', 'reason' => 'order_not_found'];
                }

                // Already cancelled/refunded - skip
                if ($order->status === Order::STATUS_CANCELLED) {
                    return ['status' => 'ignored', 'reason' => 'already_cancelled'];
                }

                // Transition to CANCELLED (triggers OrderObserver → restock)
                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancel_reason' => 'refunded',
                    'refund_transaction_id' => $transactionId,
                    'refunded_at' => now(),
                ]);

                Log::info('PaymentWebhook: Order refunded and inventory restored', [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'refund_amount' => $refundAmount,
                    'transaction_id' => $transactionId,
                ]);

                return ['status' => 'success', 'order_id' => $order->id];
            });

            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('PaymentWebhook: Server error during refund processing', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle unknown event types gracefully.
     *
     * @param string|null $eventType
     * @return JsonResponse
     */
    protected function handleUnknownEvent(?string $eventType): JsonResponse
    {
        Log::info('PaymentWebhook: Unknown event type received', [
            'event_type' => $eventType,
        ]);

        return response()->json([
            'status' => 'acknowledged',
            'message' => 'Event type not handled',
        ], 200);
    }
}
