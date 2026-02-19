<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Jobs\Payment\ProcessPaymentWebhook;
use App\Models\Payment\InboundWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Extraction
        $payload = $request->all();
        // Fallback strategy for ID extraction (adapt as needed for Stripe/PayPal)
        $providerTxId = $payload['id'] ?? $payload['data']['id'] ?? Str::uuid()->toString();
        $eventType = $payload['type'] ?? 'unknown';
        // Metadata extraction logic (or default to fallback)
        $tenantId = $payload['meta']['tenant_id'] ?? $payload['data']['object']['metadata']['tenant_id'] ?? 'unknown';

        // 2. Idempotency Gate (Read-Before-Write)
        $existing = InboundWebhook::where('provider', 'stripe') // specific provider logic
            ->where('provider_transaction_id', $providerTxId)
            ->first();

        if ($existing) {
            return response()->json(['status' => 'acknowledged_duplicate'], 200);
        }

        // 3. Persist to Inbox (Atomic Write)
        $webhook = InboundWebhook::create([
            'tenant_id' => $tenantId,
            'provider' => 'stripe', // Should be dynamic based on route/config
            'event_type' => $eventType,
            'provider_transaction_id' => $providerTxId,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        // 4. Async Handoff (Pass ID only)
        Bus::dispatch(new ProcessPaymentWebhook($webhook->id));

        return response()->json(['status' => 'queued', 'webhook_id' => $webhook->id], 200);
    }
}
