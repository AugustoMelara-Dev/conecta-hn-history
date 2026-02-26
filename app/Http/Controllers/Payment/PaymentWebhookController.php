<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Billing\Commands\ProcessInboundWebhookCommand;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PaymentWebhookController extends Controller
{
    public function __construct(
        private CommandBusInterface $commandBus
    ) {}
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Extraction
        $payload = $request->all();
        // Fallback strategy for ID extraction (adapt as needed for Stripe/PayPal)
        $providerTxId = $payload['id'] ?? $payload['data']['id'] ?? Str::uuid()->toString();
        $eventType = $payload['type'] ?? 'unknown';
        // Metadata extraction logic (or default to fallback)
        $tenantId = $payload['meta']['tenant_id'] ?? $payload['data']['object']['metadata']['tenant_id'] ?? 'unknown';

        // 3. Delegation to Application Layer via Command Bus
        // All Idempotency and Persistence is handled by the Handler
        $command = new ProcessInboundWebhookCommand(
            provider: 'stripe',
            providerTransactionId: $providerTxId,
            eventType: $eventType,
            payload: $payload,
            tenantId: $tenantId
        );

        $result = $this->commandBus->dispatch($command);

        if ($result['status'] === 'acknowledged_duplicate') {
            return response()->json(['status' => 'acknowledged_duplicate'], 200);
        }

        return response()->json(['status' => 'queued', 'webhook_id' => $result['webhook_id']], 200);
    }
}
