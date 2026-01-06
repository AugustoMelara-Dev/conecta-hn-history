<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\AIEnrichment\Jobs\GenerateBusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * AiController - The Guardian of Resources
 * 
 * Implements Non-Blocking Pipeline Pattern + Token Bucket Rate Limiting.
 */
final class AiController extends Controller
{
    /**
     * Dispatch AI generation asynchronously.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        // 1. Sanitization & Validation (Prompt Injection Defense)
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'min:3', 'max:100'],
            'activity' => ['required', 'string', 'max:50'],
            'location' => ['required', 'string', 'max:50'],
            // Optional: allow raw description if provided, but clamp it
            'extra_details' => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();
        
        if (!$user->tenant_id) {
            return response()->json(['message' => 'No business associated.'], 403);
        }

        // 2. Wallet Protection (Rate Limiting: Token Bucket)
        // Key: ai-gen:{user_id}
        // Policy: 5 requests per hour.
        $key = 'ai-gen:' . $user->id;
        
        $executed = RateLimiter::attempt(
            $key,
            $maxAttempts = 5,
            function() {
                // Determine logic executability 
                // We return true to count the attempt only if we proceed?
                // RateLimiter::attempt executes the callback if attempts remain.
                return true;
            },
            $decaySeconds = 3600 // 1 Hour
        );

        if (!$executed) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Límite de generación excedido. Por favor intenta de nuevo en " . gmdate("i:s", $seconds) . " minutos."
            ], 429);
        }

        // 3. Payload Construction
        // We compose a structured description from the rigid inputs to guide the AI
        $description = "Negocio de {$validated['activity']} ubicado en {$validated['location']}.";
        if (!empty($validated['extra_details'])) {
            $description .= " Detalles adicionales: " . $validated['extra_details'];
        }

        // 4. Non-Blocking Dispatch
        GenerateBusinessProfile::dispatch(
            $user->tenant_id,
            $validated['business_name'],
            $description
        );

        // 5. Instant Handover
        return response()->json([
            'status' => 'processing',
            'message' => 'La IA está trabajando en tu perfil. Te notificaremos en breve.',
            'remaining_attempts' => RateLimiter::remaining($key, $maxAttempts),
        ], 202);
    }
}
