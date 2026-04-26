<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BusinessEmbeddingClient
{
    /**
     * @param  list<string>  $inputs
     * @return list<list<float>>
     */
    public function embed(array $inputs): array
    {
        $safeInputs = array_values(array_filter(array_map(
            static fn (string $input): string => trim(preg_replace('/\s+/', ' ', $input) ?: $input),
            $inputs
        ), static fn (string $input): bool => $input !== ''));

        if ($safeInputs === []) {
            return [];
        }

        $model = (string) config('ai-rag.embedding_model', config('directory.guide.embedding_model', 'text-embedding-3-small'));
        $ttl = max(60, (int) config('ai-rag.embedding_cache_ttl_seconds', 900));
        $resolved = [];
        $missing = [];

        foreach ($safeInputs as $index => $input) {
            $cacheKey = $this->cacheKey($model, $input);
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && $cached !== []) {
                $resolved[$index] = array_map(static fn (mixed $value): float => (float) $value, $cached);
                continue;
            }

            $missing[$index] = $input;
        }

        if ($missing !== []) {
            $fresh = $this->fetch(array_values($missing), $model);

            foreach (array_keys($missing) as $position => $originalIndex) {
                $embedding = $fresh[$position] ?? null;

                if (! is_array($embedding) || $embedding === []) {
                    continue;
                }

                $resolved[$originalIndex] = $embedding;
                Cache::put($this->cacheKey($model, $missing[$originalIndex]), $embedding, now()->addSeconds($ttl));
            }
        }

        ksort($resolved);

        if (count($resolved) !== count($safeInputs)) {
            throw new RuntimeException('business_rag_embedding_not_found');
        }

        return array_values($resolved);
    }

    /**
     * @param  list<string>  $inputs
     * @return list<list<float>>
     */
    private function fetch(array $inputs, string $model): array
    {
        $apiKey = (string) config('services.openai.api_key', '');

        if (trim($apiKey) === '') {
            throw new RuntimeException('business_rag_openai_key_missing');
        }

        $timeout = max(3, (int) config('ai-rag.private.openai_timeout_seconds', 10));
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => $inputs,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('business_rag_embedding_failed_'.$response->status());
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new RuntimeException('business_rag_embedding_not_found');
        }

        $byIndex = [];
        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $index = (int) ($item['index'] ?? count($byIndex));
            $embedding = $item['embedding'] ?? null;

            if (is_array($embedding) && $embedding !== []) {
                $byIndex[$index] = array_map(static fn (mixed $value): float => (float) $value, $embedding);
            }
        }

        ksort($byIndex);

        return array_values($byIndex);
    }

    private function cacheKey(string $model, string $input): string
    {
        return 'business-rag:embedding:'.hash('sha256', $model.'|'.$input);
    }
}
