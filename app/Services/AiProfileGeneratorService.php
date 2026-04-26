<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Shared\Contracts\AiProfileGeneratorInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiProfileGeneratorService - OpenAI Integration for Business Profile Enrichment
 *
 * ARCHITECTURE:
 * - Defense in Depth: Multi-layer input sanitization against prompt injection.
 * - Spanish Localization: Preserves ¡¿ and tildes/ñ required for Honduran Spanish.
 * - Design by Contract: Strict validation of AI response schema and status values.
 * - Observable: Token usage metrics logged for cost auditing.
 * - Secure: XSS protection on all output fields with granular sanitization.
 * - Resilient: Rate limit state persistence to prevent cascade failures.
 *
 * USAGE:
 *   $service = app(AiProfileGeneratorService::class);
 *   $result = $service->generate($rawName, $rawDescription, $rawCategory);
 */
class AiProfileGeneratorService implements AiProfileGeneratorInterface
{
    // ═══════════════════════════════════════════════════════════════════════════════
    // CONFIGURATION CONSTANTS
    // ═══════════════════════════════════════════════════════════════════════════════

    private const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private const MODEL = 'gpt-4o-mini';

    private const MAX_TOKENS = 2000;

    private const TIMEOUT_SECONDS = 30;

    private const RETRY_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 500;

    private const MAX_INPUT_LENGTH = 2000;

    /** Cache key for rate limit state */
    private const RATE_LIMIT_CACHE_KEY = 'openai_rate_limited_until';

    /** @var array<string> Allowed status values from AI */
    private const ALLOWED_STATUSES = ['success', 'flagged'];

    /** @var array<string> Required keys in AI response */
    private const REQUIRED_KEYS = [
        'status',
        'business_name_clean',
        'tagline',
        'short_description',
        'full_description_html',
        'seo_keywords',
        'risk_analysis',
    ];

    /** @var string Whitelist of allowed HTML tags */
    private const ALLOWED_HTML_TAGS = '<p><ul><li><ol><strong><em><br>';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Actúa como un Arquitecto de Contenido Digital Senior para el mercado de Honduras. Tu objetivo es transformar datos crudos en un JSON válido. Constraints:

NO ALUCINACIONES: No inventes servicios no mencionados.

CONTEXTO CULTURAL: Usa vocabulario local (Pulpería, Truchas) y trato de 'Usted'. Usa signos de apertura ¿ y ¡ OBLIGATORIAMENTE.

SEGURIDAD: Si detectas contenido ilegal (drogas/armas/estafas), devuelve 'status': 'flagged'.

HTML: La descripción larga debe usar SOLO etiquetas <p>, <ul>, <ol>, <li>, <strong>, <em>. NO uses otras etiquetas HTML.

Schema JSON OBLIGATORIO:
{
  "status": "success" | "flagged",
  "business_name_clean": "string",
  "tagline": "string (sin HTML)",
  "short_description": "string (sin HTML, max 200 chars)",
  "full_description_html": "string (HTML permitido)",
  "seo_keywords": ["array", "de", "strings"],
  "risk_analysis": "string (evaluación de riesgo)"
}
PROMPT;

    // ═══════════════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Generate enriched business profile from raw data.
     *
     * @param  string  $rawName  Raw business name (REQUIRED - cannot be empty)
     * @param  string  $rawDescription  Raw description text
     * @param  string  $rawCategory  Category or industry
     * @return array<string, mixed> Validated and sanitized structured data
     *
     * @throws Exception If inputs invalid, API key missing, rate limited, or response invalid
     */
    public function generate(string $rawName, string $rawDescription, string $rawCategory): array
    {
        // LAYER 0: PRE-FLIGHT CHECKS
        $this->validateInputs($rawName);
        $this->checkRateLimitState();
        $apiKey = $this->getApiKey();

        // LAYER 1: INPUT SANITIZATION (Spanish-aware)
        $safeName = $this->sanitizeInput($rawName);
        $safeDescription = $this->sanitizeInput($rawDescription);
        $safeCategory = $this->sanitizeInput($rawCategory);

        $userPrompt = $this->buildUserPrompt($safeName, $safeDescription, $safeCategory);

        // LAYER 2: HTTP REQUEST
        $response = $this->executeApiRequest($apiKey, $userPrompt);

        // LAYER 3: RESPONSE PARSING
        $responseBody = $response->json();
        $this->logTokenUsage($responseBody);

        $parsed = $this->parseAiResponse($responseBody);

        // LAYER 4: CONTRACT VALIDATION
        $this->validateRequiredKeys($parsed);
        $this->validateStatusValue($parsed['status']);

        // LAYER 5: OUTPUT SANITIZATION
        return $this->sanitizeOutput($parsed);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // INPUT VALIDATION & RATE LIMIT
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Validate required inputs before processing.
     *
     * @throws Exception If business name is empty
     */
    private function validateInputs(string $rawName): void
    {
        if (trim($rawName) === '') {
            throw new Exception('Business name cannot be empty. AI enrichment requires at least a name.');
        }
    }

    /**
     * Check if we're currently rate limited.
     *
     * @throws Exception If rate limit is active
     */
    private function checkRateLimitState(): void
    {
        $rateLimitUntil = Cache::get(self::RATE_LIMIT_CACHE_KEY);

        if ($rateLimitUntil !== null) {
            $remainingSeconds = (int) max(0, now()->diffInSeconds($rateLimitUntil, absolute: false));

            if ($remainingSeconds > 0) {
                throw new Exception(
                    "OpenAI API is rate limited. Retry in {$remainingSeconds} seconds. ".
                    "Rate limit expires at: {$rateLimitUntil}"
                );
            }

            Cache::forget(self::RATE_LIMIT_CACHE_KEY);
        }
    }

    /**
     * Get and validate API key.
     *
     * @throws Exception If API key not configured
     */
    private function getApiKey(): string
    {
        $apiKey = config('services.openai.api_key') ?: config('services.openai.key');

        if (empty($apiKey)) {
            throw new Exception('OpenAI API key is not configured. Set OPENAI_API_KEY in .env');
        }

        return $apiKey;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // INPUT SANITIZATION - Spanish-Aware Unicode Regex
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Sanitize user input using Spanish-aware Unicode regex.
     *
     * CRITICAL: This regex MUST preserve:
     * - ¡ (inverted exclamation) - Required in Spanish grammar
     * - ¿ (inverted question mark) - Required in Spanish grammar
     * - á, é, í, ó, ú, ñ, ü - Spanish accented characters
     *
     * @param  string  $input  Raw user input
     * @return string Sanitized input safe for LLM prompt
     */
    private function sanitizeInput(string $input): string
    {
        // Remove control characters except newlines/tabs
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);

        // SPANISH-AWARE REGEX: Allow letters (with tildes/ñ), numbers, spaces,
        // and punctuation including ¡ and ¿ for proper Spanish grammar
        $clean = preg_replace('/[^\p{L}\p{N}\s\.,!?¡¿\-:;\'\"()\[\]@#&%]/u', '', $clean ?? '');

        // Normalize whitespace
        $clean = preg_replace('/\s+/', ' ', $clean ?? '');

        return mb_substr(trim($clean ?? ''), 0, self::MAX_INPUT_LENGTH);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // OUTPUT SANITIZATION - XSS protection
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Sanitize all output fields with granular XSS protection.
     *
     * @param  array<string, mixed>  $parsed  Raw AI response
     * @return array<string, mixed> Sanitized response
     */
    private function sanitizeOutput(array $parsed): array
    {
        // Plain text - strip ALL HTML
        $parsed['business_name_clean'] = $this->sanitizePlainText($parsed['business_name_clean']);
        $parsed['tagline'] = $this->sanitizePlainText($parsed['tagline']);
        $parsed['short_description'] = $this->sanitizePlainText($parsed['short_description']);
        $parsed['risk_analysis'] = $this->sanitizePlainText($parsed['risk_analysis']);

        // HTML - whitelist approach
        $parsed['full_description_html'] = $this->sanitizeHtml($parsed['full_description_html']);

        // Array - sanitize each element with HTML entity decoding
        $parsed['seo_keywords'] = $this->sanitizeKeywords($parsed['seo_keywords']);
        $parsed['tags'] = $this->sanitizeKeywords($parsed['tags'] ?? $parsed['seo_keywords']);

        return $parsed;
    }

    private function sanitizePlainText(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $clean = strip_tags($value);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    }

    private function sanitizeHtml(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $clean = strip_tags($value, self::ALLOWED_HTML_TAGS);
        $clean = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean) ?? '';
        $clean = preg_replace('/(javascript|data)\s*:/i', '', $clean) ?? '';
        $clean = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $clean) ?? '';

        return trim($clean);
    }

    /**
     * @return array<string>
     */
    private function sanitizeKeywords(mixed $keywords): array
    {
        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($keyword) {
            if (! is_string($keyword)) {
                return null;
            }
            $clean = html_entity_decode($keyword, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $clean = strip_tags($clean);
            $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');

            return $clean !== '' ? mb_strtolower($clean) : null;
        }, $keywords)));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // API REQUEST HANDLING
    // ═══════════════════════════════════════════════════════════════════════════════

    private function buildUserPrompt(string $name, string $description, string $category): string
    {
        return <<<PROMPT
Analiza y enriquece los siguientes datos de un negocio hondureño:

**Nombre del Negocio:** {$name}
**Categoría:** {$category}
**Descripción Original:** {$description}

Genera un JSON válido. Si el contenido es apropiado usa "status": "success". Si detectas contenido prohibido usa "status": "flagged". Recuerda usar ¿ y ¡ en español.
PROMPT;
    }

    /**
     * @throws Exception
     */
    private function executeApiRequest(string $apiKey, string $userPrompt): \Illuminate\Http\Client\Response
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function (\Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    if ($exception instanceof RequestException) {
                        $status = $exception->response->status();

                        return $status >= 500 || $status === 429;
                    }

                    return false;
                }, throw: false)
                ->post(self::OPENAI_ENDPOINT, [
                    'model' => self::MODEL,
                    'temperature' => 0.3,
                    'max_tokens' => self::MAX_TOKENS,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::error('AiProfileGeneratorService: Connection failed', [
                'context' => 'ai_connection_error',
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Unable to connect to OpenAI API. Please try again later.');
        }

        $this->handleApiErrors($response);

        return $response;
    }

    private function handleApiErrors(\Illuminate\Http\Client\Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $statusCode = $response->status();
        $errorBody = $response->body();

        if ($statusCode === 429) {
            $retryAfterHeader = $response->header('Retry-After');
            $retryAfter = $retryAfterHeader !== ''
                ? (int) $retryAfterHeader
                : 60;
            $rateLimitUntil = now()->addSeconds($retryAfter);
            Cache::put(self::RATE_LIMIT_CACHE_KEY, $rateLimitUntil, $retryAfter);

            Log::warning('AiProfileGeneratorService: Rate limited - state persisted', [
                'context' => 'ai_rate_limit',
                'retry_after_seconds' => $retryAfter,
                'rate_limit_until' => $rateLimitUntil->toIso8601String(),
            ]);

            throw new Exception("OpenAI API rate limited. Retry after {$retryAfter} seconds.");
        }

        if ($statusCode === 401) {
            throw new Exception('OpenAI API key is invalid or expired.');
        }

        if ($statusCode === 402 || $statusCode === 403) {
            throw new Exception('OpenAI API quota exceeded or access denied.');
        }

        throw new Exception("OpenAI API request failed with status {$statusCode}: ".mb_substr($errorBody, 0, 500));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // RESPONSE PARSING & VALIDATION
    // ═══════════════════════════════════════════════════════════════════════════════

    private function logTokenUsage(array $responseBody): void
    {
        $usage = $responseBody['usage'] ?? [];
        Log::info('AiProfileGeneratorService: Token usage', [
            'context' => 'ai_usage',
            'model' => self::MODEL,
            'total_tokens' => $usage['total_tokens'] ?? 0,
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
        ]);
    }

    /**
     * @throws Exception
     */
    private function parseAiResponse(array $responseBody): array
    {
        $content = $responseBody['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new Exception('OpenAI response missing expected content structure');
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('AiProfileGeneratorService: Invalid JSON from OpenAI', [
                'context' => 'ai_json_error',
                'raw_content' => mb_substr($content, 0, 500),
                'json_error' => json_last_error_msg(),
            ]);
            throw new Exception('Failed to parse OpenAI JSON response: '.json_last_error_msg());
        }

        return $parsed;
    }

    /**
     * @throws Exception
     */
    private function validateRequiredKeys(array $parsed): void
    {
        $missingKeys = array_diff(self::REQUIRED_KEYS, array_keys($parsed));

        if (! empty($missingKeys)) {
            throw new Exception('OpenAI response missing required keys: '.implode(', ', $missingKeys));
        }
    }

    /**
     * Validate status is exactly 'success' or 'flagged'.
     *
     * @throws Exception If status is any other value
     */
    private function validateStatusValue(mixed $status): void
    {
        if (! is_string($status) || ! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new Exception(
                "Invalid AI response status: '{$status}'. Must be exactly 'success' or 'flagged'."
            );
        }
    }
}
