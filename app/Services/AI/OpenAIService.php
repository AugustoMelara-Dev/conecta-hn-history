<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Domain\Billing\Services\AiCreditPricingService;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\AI\BudgetEnforcer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * OpenAIService - The Defensive Firewall + Budget Guardian
 *
 *
 * EDoS PROTECTION (2026-02-05):
 * - Créditos se RESERVAN antes de cada API call
 * - Si falla la API, se REEMBOLSA automáticamente
 * - El costo REAL se liquida post-éxito
 */
final class OpenAIService
{
    // Configuration: Retry Strategy (Milliseconds)
    private const RETRY_BACKOFF = [1000, 3000, 8000]; // 1s, 3s, 8s

    // Pricing Configuration (USD per 1M tokens) - GPT-4o
    private const PRICE_PER_1M_INPUT_TOKENS = 2.50;

    private const PRICE_PER_1M_OUTPUT_TOKENS = 10.00;

    // Estimación conservadora para reserva previa (margen de seguridad 20%)
    private const ESTIMATED_INPUT_TOKENS = 500;

    private const ESTIMATED_OUTPUT_TOKENS = 800;

    private const SAFETY_MARGIN = 1.2; // 20% buffer

    public function __construct(
        ?BudgetEnforcer $budgetEnforcer = null,
        ?AiCreditPricingService $creditPricing = null,
    ) {
        $this->budgetEnforcer = $budgetEnforcer ?? app(BudgetEnforcer::class);
        $this->creditPricing = $creditPricing ?? new AiCreditPricingService;
    }

    private readonly BudgetEnforcer $budgetEnforcer;

    private readonly AiCreditPricingService $creditPricing;

    /**
     * Calcula costo estimado para reserva previa.
     */
    private function estimateCost(): float
    {
        $inputCost = (self::ESTIMATED_INPUT_TOKENS * self::PRICE_PER_1M_INPUT_TOKENS) / 1_000_000;
        $outputCost = (self::ESTIMATED_OUTPUT_TOKENS * self::PRICE_PER_1M_OUTPUT_TOKENS) / 1_000_000;

        return ($inputCost + $outputCost) * self::SAFETY_MARGIN;
    }

    /**
     * Calcula costo real basado en tokens consumidos.
     */
    private function calculateActualCost(int $inputTokens, int $outputTokens): float
    {
        return (
            ($inputTokens * self::PRICE_PER_1M_INPUT_TOKENS) +
            ($outputTokens * self::PRICE_PER_1M_OUTPUT_TOKENS)
        ) / 1_000_000;
    }

    /**
     * Generate a structured business profile with Strict Data Contracts.
     *
     * @return array{
     *     description: string,
     *     seo_title: string,
     *     seo_description: string,
     *     tags: array,
     *     category_suggestion: string
     * }
     *
     * @throws AiProviderUnavailableException
     * @throws AiMalformedResponseException
     * @throws \App\Exceptions\InsufficientAiCreditsException
     */
    public function generateStructuredProfile(
        string $businessName,
        string $description,
        int|string $userId,
        string $tenantId,
        ?string $targetAudience = null,
        ?string $tone = null,
        ?array $keywords = []
    ): array {
        $estimatedProviderCost = $this->estimateCost();
        $estimatedCost = $this->creditPricing->fromProviderUsd($estimatedProviderCost);

        // PHASE 0: RESERVE CREDITS (EDoS Prevention)
        $reservationId = $this->budgetEnforcer->reserve(
            tenantId: $tenantId,
            estimatedCost: $estimatedCost,
            operation: 'profile_generation'
        );
        $pricingSnapshot = $this->budgetEnforcer->pricingSnapshot($tenantId, $reservationId);

        try {
            $systemPrompt = 'Eres un experto en SEO y Marketing Digital. Tu salida es JSON estricto.';

            $userPrompt = <<<EOT
Genera un perfil optimizado para el negocio "{$businessName}".
Descripción original: "{$description}".

Genera un objeto JSON con:
- description: Descripción comercial mejorada (HTML seguro permitido: <p>, <b>, <i>, <ul>, <li>).
- seo_title: Título optimizado (max 60 chars).
- seo_description: Meta descripción (max 160 chars).
- tags: Array de 5-7 etiquetas.
- category_suggestion: Categoría de negocio.
EOT;

            // MARKETING CONTEXT INJECTION (Optional Parameters)
            if ($targetAudience !== null) {
                $userPrompt .= "\n\nPúblico objetivo: {$targetAudience}";
            }
            if ($tone !== null) {
                $userPrompt .= "\nTono de comunicación: {$tone}";
            }
            if (! empty($keywords)) {
                $keywordsList = implode(', ', $keywords);
                $userPrompt .= "\nPalabras clave a incluir: {$keywordsList}";
            }

            // PHASE 1: HTTP Request with Resilient Backoff
            $attempts = 0;
            $maxAttempts = 3;
            $response = null;

            while ($attempts < $maxAttempts) {
                try {
                    $response = Http::withToken(config('services.openai.api_key'))
                        ->timeout(30)
                        ->post('https://api.openai.com/v1/chat/completions', [
                            'model' => 'gpt-4o',
                            'temperature' => 0.7,
                            'response_format' => ['type' => 'json_object'],
                            'messages' => [
                                ['role' => 'system', 'content' => $systemPrompt],
                                ['role' => 'user', 'content' => $userPrompt],
                            ],
                        ]);

                    // Defensive check: Client Errors (4xx) are strictly NOT retriable
                    if ($response->clientError()) {
                        throw new AiMalformedResponseException('OpenAI Client Error: '.$response->status());
                    }

                    // Server Errors (5xx) or Rate Limits (429) trigger manual retry
                    if ($response->serverError() || $response->status() === 429) {
                        throw new ConnectionException('Upstream Failure: '.$response->status());
                    }

                    // Success
                    break;

                } catch (ConnectionException $e) {
                    $attempts++;

                    if ($attempts >= $maxAttempts) {
                        Log::critical('[OpenAIService] Exhausted backoff strategy.', ['error' => $e->getMessage()]);
                        throw new AiProviderUnavailableException("OpenAI Unavailable after {$attempts} attempts.", 0, $e);
                    }

                    $sleepMs = self::RETRY_BACKOFF[$attempts - 1] ?? 1000;
                    usleep($sleepMs * 1000);
                } catch (Throwable $e) {
                    throw new AiProviderUnavailableException('Unexpected AI Service Failure: '.$e->getMessage(), 0, $e);
                }
            }

            // PHASE 2: THE GATEKEEPER (Schema Enforcement)
            $data = $response->json();
            $contentRaw = $data['choices'][0]['message']['content'] ?? '{}';
            $decoded = json_decode($contentRaw, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw new AiMalformedResponseException('Invalid JSON structure received.');
            }

            // SCHEMA VALIDATION (Strict Type Enforcement)
            $validator = Validator::make($decoded, [
                'description' => 'required|string',
                'seo_title' => 'required|string|max:70',
                'seo_description' => 'required|string|max:160',
                'tags' => 'required|array|min:3|max:10',
                'tags.*' => 'string|max:30',
                'category_suggestion' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors()->all();
                Log::error('[OpenAIService] Schema Violation', ['errors' => $errors, 'payload' => $decoded]);
                throw new AiMalformedResponseException('AI returned invalid schema: '.implode(', ', $errors));
            }

            // PHASE 3: THE CLEANER (XSS Sanitization)
            $cleanDescription = strip_tags(
                $decoded['description'],
                ['p', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'br']
            );

            $decoded['description'] = $cleanDescription;

            // PHASE 4: SETTLE RESERVATION + FINANCIAL AUDIT (ATOMIC)
            $usage = $data['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];
            $inputTokens = $usage['prompt_tokens'];
            $outputTokens = $usage['completion_tokens'];
            $actualProviderCost = $this->calculateActualCost($inputTokens, $outputTokens);
            $actualCost = $this->creditPricing->fromProviderUsdAtSnapshot(
                $actualProviderCost,
                $pricingSnapshot['credits_per_provider_usd'],
                $pricingSnapshot['minimum_charge'],
            );

            // Liquidar reserva con costo real
            $this->budgetEnforcer->settle(
                tenantId: $tenantId,
                estimatedCost: $estimatedCost,
                actualCost: $actualCost,
                reservationId: $reservationId,
                usageMetadata: [
                    'model' => 'gpt-4o',
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'operation' => 'profile_generation',
                    'provider_cost_usd' => $actualProviderCost,
                    'credit_pricing_version' => $pricingSnapshot['version'],
                    'credits_per_provider_usd' => $pricingSnapshot['credits_per_provider_usd'],
                    'credit_minimum_charge' => $pricingSnapshot['minimum_charge'],
                ]
            );

            // Auditoría detallada (complementa al settle)
            try {
                DB::table('ai_usage_logs')->insert([
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'provider' => 'openai',
                    'model' => 'gpt-4o',
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'cost' => $actualProviderCost,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::emergency('[OpenAIService] Failed to log usage cost!', ['user_id' => $userId, 'cost' => $actualProviderCost]);
            }

            return $decoded;

        } catch (Throwable $e) {
            // FAILURE RECOVERY: Refund the reservation
            $this->budgetEnforcer->refund(
                tenantId: $tenantId,
                estimatedCost: $estimatedCost,
                reservationId: $reservationId,
                failureReason: $e->getMessage()
            );

            throw $e; // Re-throw para que el caller maneje el error
        }
    }
}
