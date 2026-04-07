<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domain\TenantGovernance\Services\CustomerTouchpointEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResendWebhookController
{
    public function __construct(
        private readonly CustomerTouchpointEventRecorder $recorder,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.resend.webhook_secret', '');
        $payload = $request->getContent();
        $headers = collect($request->headers->all())
            ->map(static fn (array $value): string => (string) ($value[0] ?? ''))
            ->mapWithKeys(static fn (string $value, string $key): array => [strtolower($key) => $value])
            ->all();

        if ($secret === '') {
            return response()->json([
                'message' => 'Resend webhook secret is not configured.',
            ], 500);
        }

        try {
            \Resend::client((string) config('services.resend.key', ''))
                ->webhooks
                ->verify($payload, $headers, $secret);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
                'error' => $exception->getMessage(),
            ], 401);
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return response()->json([
                'message' => 'Invalid webhook payload.',
            ], 422);
        }

        $event = $this->recorder->recordResendWebhook($decoded, $headers);

        return response()->json([
            'ok' => true,
            'tracked' => $event !== null,
        ]);
    }
}
