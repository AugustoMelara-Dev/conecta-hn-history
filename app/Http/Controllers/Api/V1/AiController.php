<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\PrivateAiAccessGate;
use App\Domain\AIEnrichment\Prompts\BusinessProfilePromptBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class AiController extends Controller
{
    public function __construct(
        private readonly PrivateAiAccessGate $privateAiAccess,
    ) {}

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'min:3', 'max:100'],
            'activity' => ['required', 'string', 'max:50'],
            'location' => ['required', 'string', 'max:50'],
            'extra_details' => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();

        if (! $user || ! $user->tenant_id) {
            return response()->json(['message' => 'No business associated.'], 403);
        }

        $tenantId = (string) $user->tenant_id;

        if (! $this->privateAiAccess->allows($user, $tenantId)) {
            return response()->json([
                'message' => 'Private AI is not enabled for this tenant.',
            ], 403);
        }

        $key = 'ai-gen:'.$tenantId.':'.$user->id;
        $maxAttempts = 5;

        $executed = RateLimiter::attempt($key, $maxAttempts, static fn (): bool => true, 3600);

        if (! $executed) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Limite de generacion excedido. Intenta de nuevo en '.gmdate('i:s', $seconds).' minutos.',
            ], 429);
        }

        $payload = BusinessProfilePromptBuilder::build(
            businessName: $validated['business_name'],
            activity: $validated['activity'],
            location: $validated['location'],
            extraDetails: $validated['extra_details'] ?? null
        );

        return response()->json([
            'status' => 'ready',
            'message' => 'Borrador generado con reglas locales. La generacion con proveedor externo permanece controlada por gates privados.',
            'draft' => [
                'business_name' => $validated['business_name'],
                'summary' => $payload->content,
            ],
            'remaining_attempts' => RateLimiter::remaining($key, $maxAttempts),
        ]);
    }
}
