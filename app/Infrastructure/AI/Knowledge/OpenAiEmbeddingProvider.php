<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Knowledge;

use App\Application\Ai\Knowledge\EmbeddingProvider;
use App\Application\Ai\Knowledge\EmbeddingResult;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenAiEmbeddingProvider implements EmbeddingProvider
{
    public function embed(string $content): EmbeddingResult
    {
        $content = trim($content);
        $apiKey = trim((string) config('services.openai.api_key', ''));
        $endpoint = trim((string) config('services.openai.embeddings_endpoint', ''));
        $model = trim((string) config('ai.knowledge.model', ''));
        $dimensions = (int) config('ai.knowledge.embedding_dimensions', 1536);

        if ($content === '' || mb_strlen($content) > 8000) {
            throw new AiMalformedResponseException('Embedding input is outside allowed bounds.');
        }

        if ($apiKey === '' || $model === '' || $dimensions < 1 || $dimensions > 2000 || ! $this->isHttpsUrl($endpoint)) {
            throw new AiProviderUnavailableException('Embedding provider is not configured.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(max(1, (int) ceil((int) config('ai.limits.timeout_milliseconds', 15000) / 1000)))
                ->retry(2, 200, throw: false)
                ->post($endpoint, [
                    'input' => $content,
                    'model' => $model,
                    'dimensions' => $dimensions,
                    'encoding_format' => 'float',
                ]);
        } catch (Throwable) {
            throw new AiProviderUnavailableException('Embedding provider is temporarily unavailable.');
        }

        if (! $response->successful()) {
            throw new AiProviderUnavailableException('Embedding provider is temporarily unavailable.');
        }

        $payload = $response->json();
        $vector = is_array($payload) ? ($payload['data'][0]['embedding'] ?? null) : null;
        $responseModel = is_array($payload) ? ($payload['model'] ?? null) : null;
        $inputTokens = is_array($payload) ? ($payload['usage']['prompt_tokens'] ?? null) : null;

        if (! is_array($vector)
            || count($vector) !== $dimensions
            || ! is_string($responseModel)
            || trim($responseModel) === ''
            || ! is_int($inputTokens)
            || $inputTokens < 0) {
            throw new AiMalformedResponseException('Embedding provider returned invalid metadata.');
        }

        $normalized = [];
        foreach ($vector as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new AiMalformedResponseException('Embedding provider returned an invalid vector.');
            }

            $normalized[] = (float) $value;
        }

        return new EmbeddingResult($normalized, $responseModel, $inputTokens);
    }

    private function isHttpsUrl(string $endpoint): bool
    {
        return filter_var($endpoint, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) === 'https';
    }
}
