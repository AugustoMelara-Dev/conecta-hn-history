<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Application\Billing\Actions\HandlePaymentWebhookAction;
use App\Domain\ValueObjects\Payment\PaymentWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Psr\Log\LoggerInterface;

/**
 * PaymentWebhookController - HTTP Gateway for Payment Callbacks
 *
 * REFACTORED: Domain Action Pattern Implemented
 * VALIDATION: Signature verification delegated internally
 * ACID COMPLIANCE: Delegate to Transactional Action
 */
final class PaymentWebhookController extends Controller
{
    public function __construct(
        private HandlePaymentWebhookAction $webhookAction,
        private LoggerInterface $logger,
        private string $webhookSecret,
    ) {}

    /**
     * Handle incoming webhook payload from payment provider.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Verify Signature
        if (! $this->verifySignature($request)) {
            $this->logger->warning('PaymentWebhook: Invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payload = $request->json()->all();
        $eventType = $payload['event'] ?? null;
        $eventData = $payload['data'] ?? [];

        $this->logger->info('PaymentWebhook: Event received', [
            'event_type' => $eventType,
            'transaction_id' => $eventData['transaction_id'] ?? 'N/A',
        ]);

        try {
            // 2. Delegate to Transactional Action
            $webhookDto = PaymentWebhook::fromArray(
                (string) ($eventType ?? 'unknown'),
                $payload
            );

            $result = $this->webhookAction->handle($webhookDto);

            // Map result to status code
            $status = match ($result['status'] ?? 'ok') {
                'ok' => 200,
                'ignored' => 202,
                'invalid' => 422,
                default => 200,
            };

            return response()->json($result, $status);

        } catch (\Throwable $e) {
            $this->logger->error('PaymentWebhook: Processing Error', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Verify the HMAC signature from the request using strict comparison.
     */
    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Signature');

        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', (string) $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, (string) $signature);
    }
}
