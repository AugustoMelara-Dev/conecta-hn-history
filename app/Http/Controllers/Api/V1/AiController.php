<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Jobs\AI\GenerateBusinessProfile;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * AiController - The Guardian of Resources
 *
 * Implements Non-Blocking Pipeline Pattern + Token Bucket Rate Limiting.
 */
final class AiController extends Controller
{
    public function __construct(
        private RateLimiter $limiter
    ) {}

    /**
     * Dispatch AI generation asynchronously.
     */
    #[OA\Post(
        path: '/api/ai/generate',
        summary: 'Generate AI Profile',
        tags: ['AI'],
        requestBody: new OA\RequestBody(required: true, description: 'AI generation payload containing business details'),
        responses: [
            new OA\Response(response: 202, description: 'Processing Accepted'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 429, description: 'Too Many Requests'),
        ]
    )]
    public function generate(Request $request): JsonResponse
    {
        // 1. Sanitization & Validation (Framework Concern)
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'min:3', 'max:100'],
            'activity' => ['required', 'string', 'max:50'],
            'location' => ['required', 'string', 'max:50'],
            'extra_details' => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();

        if (! $user->tenant_id) {
            return response()->json(['message' => 'No business associated.'], 403);
        }

        // 2. Rate Limiting (Token Bucket Strategy)
        $key = 'ai-gen:'.$user->id;

        $executed = $this->limiter->attempt(
            $key,
            $maxAttempts = 5,
            function () {
                return true;
            },
            $decaySeconds = 3600
        );

        if (! $executed) {
            $seconds = $this->limiter->availableIn($key);

            return response()->json([
                'message' => 'Límite de generación excedido. Intenta de nuevo en '.gmdate('i:s', $seconds).' minutos.',
            ], 429);
        }

        // 3. Domain Service Delegation (No "Primitive Obsession" here)
        // Controller looks for a structured DTO, agnostic of text content.
        $payload = \App\Domain\AIEnrichment\Prompts\BusinessProfilePromptBuilder::build(
            businessName: $validated['business_name'],
            activity: $validated['activity'],
            location: $validated['location'],
            extraDetails: $validated['extra_details'] ?? null
        );

        // 4. Async Dispatch using Clean DTO Content
        GenerateBusinessProfile::dispatch(
            $user->tenant_id,
            $validated['business_name'],
            $payload->content
        );

        // 5. Response
        return response()->json([
            'status' => 'processing',
            'message' => 'La IA está trabajando en tu perfil. Te notificaremos en breve.',
            'remaining_attempts' => $this->limiter->remaining($key, $maxAttempts),
        ], 202);
    }
}
