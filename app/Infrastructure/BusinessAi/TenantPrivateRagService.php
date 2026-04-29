<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use App\Application\BusinessAi\Contracts\BusinessRagServiceInterface;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

final class TenantPrivateRagService implements BusinessRagServiceInterface
{
    public function __construct(
        private readonly BusinessEmbeddingClient $embeddingClient,
        private readonly PrivateRagRedactor $redactor,
        private readonly TenantPrivateSourceRetriever $retriever,
        private readonly TenantPrivateAnswerGenerator $answerGenerator,
        private readonly TenantPrivateRagAuditor $auditor,
    ) {}

    public function answer(Tenant $tenant, string $query, ?string $userId = null, int $limit = 6): array
    {
        $startedAt = microtime(true);
        $safeQuery = $this->normalizeQuery($query);
        $safeLimit = max(1, min($limit, (int) config('ai-rag.private.max_sources', 8)));
        $degradedReason = null;

        if (! Schema::hasTable('tenant_knowledge_embeddings')) {
            throw new \RuntimeException('tenant_knowledge_embeddings_table_missing');
        }

        $sources = [];

        try {
            $queryEmbedding = $this->embeddingClient->embed([$safeQuery])[0] ?? [];
            if ($queryEmbedding !== []) {
                $sources = $this->retriever->retrieveByVectorStore($tenant, $queryEmbedding, $safeLimit);

                if ($sources === []) {
                    $sources = $this->retriever->retrieveByEmbedding($tenant, $queryEmbedding, $safeLimit);
                }
            }
        } catch (\Throwable $exception) {
            $degradedReason = $exception->getMessage();
        }

        if ($sources === []) {
            $sources = $this->retriever->retrieveLexically($tenant, $safeQuery, $safeLimit);
            $degradedReason ??= 'lexical_retrieval';
        }

        $answer = $this->answerGenerator->generate($tenant, $safeQuery, $sources, $degradedReason, $userId);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->auditor->audit($tenant, $userId, $safeQuery, $answer, $sources, $latencyMs, $degradedReason, 200);

        return [
            'answer' => $answer,
            'sources' => $this->auditor->sourcePayload($sources),
            'meta' => [
                'scope' => 'tenant_private',
                'tenant_id' => (string) $tenant->getKey(),
                'source_count' => count($sources),
                'degraded_reason' => $degradedReason,
                'latency_ms' => $latencyMs,
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function normalizeQuery(string $query): string
    {
        $query = $this->redactor->redactText($query);
        $query = preg_replace('/\s+/', ' ', $query) ?? $query;

        return trim(mb_substr($query, 0, (int) config('ai-rag.private.max_query_chars', 500)));
    }
}
