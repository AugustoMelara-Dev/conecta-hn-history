<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Domain\TenantGovernance\Services\CustomerTouchpointEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class WhatsAppCloudWebhookController
{
    public function __construct(
        private readonly CustomerTouchpointEventRecorder $recorder,
    ) {}

    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', '');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');
        $verifyToken = (string) config('services.whatsapp_cloud.verify_token', '');

        if ($mode !== 'subscribe' || $verifyToken === '' || ! hash_equals($verifyToken, $token)) {
            abort(403, 'Invalid WhatsApp webhook verification token.');
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $headers = collect($request->headers->all())
            ->map(static fn (array $value): string => (string) ($value[0] ?? ''))
            ->mapWithKeys(static fn (string $value, string $key): array => [strtolower($key) => $value])
            ->all();

        $this->verifySignature($payload, $headers);

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return response()->json([
                'message' => 'Invalid WhatsApp webhook payload.',
            ], 422);
        }

        $tracked = $this->recorder->recordWhatsAppWebhook($decoded, $headers);

        return response()->json([
            'ok' => true,
            'tracked' => $tracked,
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function verifySignature(string $payload, array $headers): void
    {
        $appSecret = trim((string) config('services.whatsapp_cloud.app_secret', ''));
        $signature = (string) ($headers['x-hub-signature-256'] ?? '');

        if ($appSecret === '') {
            abort(500, 'WhatsApp webhook app secret is not configured.');
        }

        if ($signature === '') {
            abort(401, 'Missing WhatsApp webhook signature.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $appSecret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid WhatsApp webhook signature.');
        }
    }
}
