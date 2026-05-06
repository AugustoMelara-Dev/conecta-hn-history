<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Application\Actions\Payment\HandlePaymentWebhookAction;
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
 * ║  ACID COMPLIANCE: Delegate to Transactional Action                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PaymentWebhookController extends Controller
{
    /**
     * Webhook secret for verification.
     */
    protected string $webhookSecret;

    public function __construct(
        private readonly HandlePaymentWebhookAction $action
    ) {
        $secret = config('services.payment.webhook_secret');

        if (empty($secret) && app()->runningInConsole()) {
            $secret = 'console-dummy-secret';
        }

        throw_if(
            empty($secret),
            \RuntimeException::class,
            'PAYMENT_WEBHOOK_SECRET is not configured.'
        );

        $this->webhookSecret = (string) $secret;
    }

    /**
     * Handle incoming payment webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Verify Signature
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

        try {
            // 2. Delegate to Transactional Action
            $result = $this->action->execute($eventType ?? 'unknown', $eventData);

            // Map result to status code
            $status = match ($result['status'] ?? 'ok') {
                'success', 'acknowledged', 'ignored' => 200,
                default => 500
            };

            return response()->json($result, $status);

        } catch (\Throwable $e) {
            Log::error('PaymentWebhook: Processing Error', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    protected function verifySignature(Request $request): bool
    {
        $providedSignature = $request->header('X-Webhook-Signature');

        if (empty($providedSignature)) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $providedSignature);
    }
}
