<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Application\Billing\Actions\HandlePaymentWebhookAction;
use App\Domain\ValueObjects\Payment\PaymentWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

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
        $verifiedPayload = $this->verifiedPayload($request);

        if ($verifiedPayload === null) {
            $this->logger->warning('PaymentWebhook: Invalid signature', [
                'ip' => $request->ip(),
                'provider' => config('services.payment.provider', 'generic'),
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payload = $verifiedPayload;
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

        } catch (InvalidArgumentException $e) {
            $this->logger->warning('PaymentWebhook: Invalid payload', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'invalid',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            $this->logger->error('PaymentWebhook: Processing Error', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    private function verifiedPayload(Request $request): ?array
    {
        if ($this->isStripeWebhook($request)) {
            return $this->verifiedStripePayload($request);
        }

        if (! $this->verifyGenericSignature($request)) {
            return null;
        }

        $payload = $request->json()->all();

        return is_array($payload) ? $payload : null;
    }

    private function isStripeWebhook(Request $request): bool
    {
        return strtolower((string) config('services.payment.provider', 'generic')) === 'stripe'
            || $request->headers->has('Stripe-Signature');
    }

    /**
     * Stripe requires the unmodified raw body plus Stripe-Signature header.
     *
     * @return array<string, mixed>|null
     */
    private function verifiedStripePayload(Request $request): ?array
    {
        $signature = (string) $request->header('Stripe-Signature', '');

        if ($signature === '') {
            return null;
        }

        try {
            $this->verifyStripeSignature($request->getContent(), $signature);
            $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (UnexpectedValueException|\JsonException $exception) {
            $this->logger->warning('PaymentWebhook: Stripe verification failed', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $payload['event'] = (string) ($payload['type'] ?? 'unknown');
        $payload['provider'] = 'stripe';
        $payload['meta'] = array_replace(
            (array) ($payload['meta'] ?? []),
            ['provider' => 'stripe'],
        );

        return $payload;
    }

    private function verifyStripeSignature(string $payload, string $signatureHeader): void
    {
        $parts = [];

        foreach (explode(',', $signatureHeader) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, null);

            if (is_string($key) && is_string($value) && $key !== '') {
                $parts[$key][] = $value;
            }
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if (! is_string($timestamp) || $timestamp === '' || $signatures === []) {
            throw new UnexpectedValueException('Malformed Stripe signature header.');
        }

        if (! $this->isFreshTimestamp($timestamp)) {
            throw new UnexpectedValueException('Stale Stripe signature timestamp.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return;
            }
        }

        throw new UnexpectedValueException('No matching Stripe signature.');
    }

    /**
     * Verify the generic HMAC signature from the request using strict comparison.
     */
    private function verifyGenericSignature(Request $request): bool
    {
        $signature = $request->header('X-Signature');

        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $timestamp = $request->header('X-Timestamp') ?? $request->header('X-Webhook-Timestamp');

        if (is_string($timestamp) && $timestamp !== '') {
            if (! $this->isFreshTimestamp($timestamp)) {
                return false;
            }

            $signedPayload = $timestamp.'.'.(string) $payload;
            $timestampedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

            if (hash_equals($timestampedSignature, (string) $signature)) {
                return true;
            }
        }

        if ((bool) config('services.payment.webhook_require_timestamp', false)) {
            return false;
        }

        $legacySignature = hash_hmac('sha256', (string) $payload, $this->webhookSecret);

        return hash_equals($legacySignature, (string) $signature);
    }

    private function isFreshTimestamp(string $timestamp): bool
    {
        try {
            $moment = is_numeric($timestamp)
                ? Carbon::createFromTimestamp((int) $timestamp)
                : Carbon::parse($timestamp);
        } catch (\Throwable) {
            return false;
        }

        $toleranceSeconds = (int) config('services.payment.webhook_timestamp_tolerance_seconds', 300);

        return $moment->betweenIncluded(
            now()->subSeconds($toleranceSeconds),
            now()->addSeconds(30),
        );
    }
}
