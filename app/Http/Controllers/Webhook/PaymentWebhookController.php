<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

    protected \App\Services\WebhookService $webhookService;

    public function __construct(\App\Services\WebhookService $webhookService)
    {
        // Zero Trust: Fail immediately if webhook secret is not configured
        $secret = config('services.payment.webhook_secret');

        throw_if(
            empty($secret),
            \RuntimeException::class,
            'PAYMENT_WEBHOOK_SECRET is not configured. Refusing to process webhooks without explicit secret.'
        );

        $this->webhookSecret = (string) $secret;
        $this->webhookService = $webhookService;
    }

    /**
     * Handle incoming payment webhook.
     *
     * @param  Request  $request  The raw webhook request
     */
    public function handle(Request $request): JsonResponse
    {
        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: ZERO-TRUST SIGNATURE VERIFICATION
        // ═══════════════════════════════════════════════════════════════════════
        // The gatekeeper pattern: verify origin before parsing payload.
        if (! $this->verifySignature($request)) {
            Log::warning('PaymentWebhook: Invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

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
     * Delegated to WebhookService.
     */
    protected function handlePaymentSucceeded(array $data): JsonResponse
    {
        try {
            $result = $this->webhookService->handlePaymentSucceeded($data);
            $status = ($result['status'] === 'success' || $result['status'] === 'ignored') ? 200 : 500;

            return response()->json($result, $status);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle failed payment event.
     *
     * Order remains in PENDING status, awaiting retry or cancellation.
     *
     * @param  array  $data  Event payload data
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
     * Delegated to WebhookService for Architecture Compliance.
     */
    protected function handlePaymentRefunded(array $data): JsonResponse
    {
        try {
            $result = $this->webhookService->handlePaymentRefunded($data);
            $status = ($result['status'] === 'success' || $result['status'] === 'ignored') ? 200 : 500;

            return response()->json($result, $status);
        } catch (\Exception $e) {
            // Logged in Service
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle unknown event types gracefully.
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
