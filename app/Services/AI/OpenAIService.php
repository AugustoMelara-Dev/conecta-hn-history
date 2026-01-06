<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * OpenAIService - The Defensive Firewall
 *
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY ARCHITECTURE: DATA FIREWALL                                     ║
 * ╠═══════════════════════════════════════════════════════════════════════════╣
 * ║  1. Gatekeeper: Draconian Schema Validation via Validator facade.         ║
 * ║  2. Sanitizer: Surgical removal of XSS vectors (strip_tags whitelist).    ║
 * ║  3. Resilience: Configurable Exponential Backoff Strategy.                ║
 * ║  4. Audit: Atomic financial logging of every token consumed.              ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 */
final class OpenAIService
{
    // Configuration: Retry Strategy (Milliseconds)
    private const RETRY_BACKOFF = [1000, 3000, 8000]; // 1s, 3s, 8s

    // Pricing Configuration (USD per 1M tokens) - GPT-4o
    private const PRICE_PER_1M_INPUT_TOKENS = 2.50;
    private const PRICE_PER_1M_OUTPUT_TOKENS = 10.00;

    /**
     * Generate a structured business profile with Strict Data Contracts.
     *
     * @param string $businessName
     * @param string $description
     * @param int $userId
     * @param int $tenantId
     * @return array{
     *     description: string,
     *     seo_title: string,
     *     seo_description: string,
     *     tags: array,
     *     category_suggestion: string
     * }
     * @throws AiProviderUnavailableException
     * @throws AiMalformedResponseException
     */
    public function generateStructuredProfile(string $businessName, string $description, int $userId, int $tenantId): array
    {
        $systemPrompt = "Eres un experto en SEO y Marketing Digital. Tu salida es JSON estricto.";
        
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

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: HTTP Request with Resilient Backoff
        // ═══════════════════════════════════════════════════════════════════════
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

                // Defensiive check: Client Errors (4xx) are strictly NOT retriable
                if ($response->clientError()) {
                    throw new AiMalformedResponseException("OpenAI Client Error: " . $response->status());
                }

                // Server Errors (5xx) or Rate Limits (429) trigger manual retry
                if ($response->serverError() || $response->status() === 429) {
                    throw new ConnectionException("Upstream Failure: " . $response->status());
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
                usleep($sleepMs * 1000); // usleep takes microseconds
            } catch (Throwable $e) {
                // Unexpected Fatal Error
                throw new AiProviderUnavailableException("Unexpected AI Service Failure: " . $e->getMessage(), 0, $e);
            }
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: THE GATEKEEPER (Schema Enforcement)
        // ═══════════════════════════════════════════════════════════════════════
        $data = $response->json();
        $contentRaw = $data['choices'][0]['message']['content'] ?? '{}';
        $decoded = json_decode($contentRaw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new AiMalformedResponseException("Invalid JSON structure received.");
        }

        // SCHEMA VALIDATION (Strict Type Enforcement)
        $validator = Validator::make($decoded, [
            'description'       => 'required|string',
            'seo_title'         => 'required|string|max:70',
            'seo_description'   => 'required|string|max:160',
            'tags'              => 'required|array|min:3|max:10',
            'tags.*'            => 'string|max:30', // Nested array validation
            'category_suggestion'=> 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            Log::error('[OpenAIService] Schema Violation', ['errors' => $errors, 'payload' => $decoded]);
            throw new AiMalformedResponseException("AI returned invalid schema: " . implode(", ", $errors));
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 3: THE CLEANER (XSS Sanitization)
        // ═══════════════════════════════════════════════════════════════════════
        // Whitelist: p, b, strong, i, em, u, ul, ol, li, br
        // Any other tag (script, iframe, object, img) is obliterated.
        $cleanDescription = strip_tags(
            $decoded['description'], 
            ['p', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'br']
        );

        $decoded['description'] = $cleanDescription;

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 4: FINANCIAL AUDIT
        // ═══════════════════════════════════════════════════════════════════════
        $usage = $data['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0];
        $inputTokens = $usage['prompt_tokens'];
        $outputTokens = $usage['completion_tokens'];

        $cost = (
            ($inputTokens * self::PRICE_PER_1M_INPUT_TOKENS) +
            ($outputTokens * self::PRICE_PER_1M_OUTPUT_TOKENS)
        ) / 1000000;

        try {
            DB::table('ai_usage_logs')->insert([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost' => $cost,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::emergency('[OpenAIService] Failed to log usage cost!', ['user_id' => $userId, 'cost' => $cost]);
        }

        return $decoded; // Returns validated, sanitized, and audited data.
    }
}
