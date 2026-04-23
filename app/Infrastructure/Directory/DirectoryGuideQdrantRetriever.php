<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DirectoryGuideQdrantRetriever
{
    public function __construct(
        private readonly QdrantClient $qdrantClient
    ) {}

    /**
     * @return list<int>
     */
    public function resolveProductHints(
        string $query,
        float $budgetAmount,
        string $currency,
        ?string $citySlug,
        ?string $categorySlug,
        int $limit,
    ): array {
        $normalizedQuery = trim($query);

        if (! $this->qdrantClient->enabled() || $normalizedQuery === '' || $budgetAmount <= 0) {
            return [];
        }

        $queryEmbedding = $this->embedQuery($normalizedQuery);

        return $this->qdrantClient->searchProductIds(
            queryVector: $queryEmbedding,
            budgetAmount: $budgetAmount,
            currency: $currency,
            citySlug: $citySlug,
            categorySlug: $categorySlug,
            limit: max($limit * 3, (int) config('directory.guide.qdrant.max_candidates', 24)),
        );
    }

    /**
     * @return list<float>
     */
    private function embedQuery(string $query): array
    {
        $model = (string) config('directory.guide.embedding_model', 'text-embedding-3-small');
        $cacheTtl = max(30, (int) config('directory.guide.embedding_cache_ttl_seconds', 300));
        $cacheKey = 'directory:qdrant:query-embedding:'.hash('sha256', $model.'|'.$query);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            return array_map(static fn (mixed $value): float => (float) $value, $cached);
        }

        $apiKey = (string) config('services.openai.api_key', '');

        if (trim($apiKey) === '') {
            throw new RuntimeException('openai_auth_failed');
        }

        $timeout = max(3, (int) config('directory.guide.openai_timeout_seconds', 8));
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => [$query],
            ]);

        if (! $response->ok()) {
            $status = $response->status();

            if ($status === 401 || $status === 403) {
                throw new RuntimeException('openai_auth_failed');
            }

            throw new RuntimeException('openai_timeout');
        }

        $embedding = $response->json('data.0.embedding');

        if (! is_array($embedding) || $embedding === []) {
            throw new RuntimeException('embedding_not_found');
        }

        $resolved = array_map(static fn (mixed $value): float => (float) $value, $embedding);
        Cache::put($cacheKey, $resolved, now()->addSeconds($cacheTtl));

        return $resolved;
    }
}
