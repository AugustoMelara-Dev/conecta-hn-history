<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\OpenAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AiOptimizationController - Public Facade for AI Services
 *
 * Exposes internal AI capabilities to the frontend via secure, rate-limited endpoints.
 */
final class AiOptimizationController extends Controller
{
    public function __construct(
        private readonly OpenAIService $aiService
    ) {}

    /**
     * Optimizes a business profile (SEO, Tags, Description) using GPT-4o.
     */
    public function optimizeProfile(Request $request): JsonResponse
    {
        $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $user = Auth::user();
        if (! $user->tenant_id) {
            return response()->json(['message' => 'No tienes un negocio asociado.'], 403);
        }

        try {
            $optimizedData = $this->aiService->generateStructuredProfile(
                businessName: $request->input('business_name'),
                description: $request->input('description'),
                userId: $user->id,
                tenantId: $user->tenant_id
            );

            return response()->json([
                'success' => true,
                'data' => $optimizedData,
            ]);

        } catch (\App\Exceptions\Ai\AiProviderUnavailableException $e) {
            // Semantic: "Service Unavailable" (503)
            return response()->json([
                'success' => false,
                'message' => 'El servicio de IA está temporalmente saturado. Por favor intenta en unos minutos.',
            ], 503);

        } catch (\App\Exceptions\Ai\AiMalformedResponseException $e) {
            // Semantic: "Unprocessable Entity" (422) - Data Integrity Fail
            Log::error('[AiController] Malformed Response', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No pudimos generar un perfil válido. Por favor mejora la descripción e intenta de nuevo.',
            ], 422);

        } catch (\Throwable $e) {
            Log::error('[AiController] Unexpected Error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno procesando la solicitud.',
            ], 500);
        }
    }
}
