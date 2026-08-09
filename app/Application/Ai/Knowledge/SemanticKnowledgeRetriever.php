<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge;

use App\Infrastructure\AI\Knowledge\EloquentKnowledgeIndexRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SemanticKnowledgeRetriever implements KnowledgeRetriever
{
    public function __construct(
        private EmbeddingUsageMeter $embeddings,
        private EloquentKnowledgeIndexRepository $index,
        private KnowledgeQueryIngressGuard $ingress,
    ) {}

    public function searchPublic(string $query, int $limit = 12): array
    {
        return $this->search($query, null, true, $limit);
    }

    public function searchTenant(string $query, string $tenantId, int $limit = 12): array
    {
        return $this->search($query, $tenantId, true, $limit);
    }

    /** @return list<array<string, mixed>> */
    private function search(string $query, ?string $tenantId, bool $includePublic, int $limit): array
    {
        if (! config('ai.knowledge.enabled', false)) {
            return [];
        }

        try {
            $redactedQuery = $tenantId === null
                ? $this->ingress->inspectPublic($query)
                : $this->ingress->inspectTenant($query, $tenantId);
            if ($redactedQuery === null) {
                return [];
            }
            $billingTenantId = $tenantId ?? trim((string) config('ai.public.billing_tenant_id', ''));
            if ($billingTenantId === '') {
                return [];
            }
            $embedding = $this->embeddings->embed(
                $billingTenantId,
                $tenantId === null ? 'knowledge.query.public' : 'knowledge.query.private',
                $redactedQuery,
            );
            $minimum = min(1.0, max(-1.0, (float) config('ai.knowledge.minimum_similarity', 0.55)));
            $boundedLimit = min(20, max(1, $limit));

            return collect($this->index->search(
                $embedding->vector,
                $tenantId,
                $includePublic,
                $boundedLimit,
            ))
                ->filter(static fn (array $row): bool => (float) ($row['similarity'] ?? -1) >= $minimum)
                ->values()
                ->map(static fn (array $row, int $index): array => [
                    'evidence_id' => (string) ($row['resource_type'] ?? '').':'.(string) ($row['resource_id'] ?? ''),
                    'source_tenant_id' => (string) ($row['tenant_id'] ?? ''),
                    'resource_type' => (string) ($row['resource_type'] ?? ''),
                    'resource_id' => (string) ($row['resource_id'] ?? ''),
                    'source_version' => (string) ($row['content_hash'] ?? ''),
                    'visibility' => (string) ($row['visibility'] ?? ''),
                    'fresh_at' => (string) ($row['indexed_at'] ?? ''),
                    'retrieval_rank' => $index + 1,
                    'similarity' => (float) ($row['similarity'] ?? 0),
                    'excerpt_untrusted' => mb_substr(strip_tags((string) ($row['content'] ?? '')), 0, 500),
                ])
                ->filter(static fn (array $row): bool => in_array($row['resource_type'], ['product', 'service'], true)
                    && $row['resource_id'] !== '')
                ->values()
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Semantic knowledge retrieval degraded safely.', [
                'exception_class' => $exception::class,
                'scope' => $tenantId === null ? 'public' : 'tenant',
            ]);

            return [];
        }
    }
}
