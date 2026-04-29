<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantPrivateSourceRetriever
{
    public function __construct(
        private readonly PrivateBusinessQdrantClient $qdrantClient,
        private readonly PrivateRagRedactor $redactor,
    ) {}

    /**
     * @param  list<float>  $queryEmbedding
     * @return list<array<string,mixed>>
     */
    public function retrieveByVectorStore(Tenant $tenant, array $queryEmbedding, int $limit): array
    {
        if (! $this->qdrantClient->enabled()) {
            return [];
        }

        $candidates = $this->qdrantClient->searchTenantSources(
            tenant: $tenant,
            queryVector: $queryEmbedding,
            limit: max($limit, (int) config('ai-rag.private.qdrant.max_candidates', 24)),
        );

        if ($candidates === []) {
            return [];
        }

        $sourcePairs = collect($candidates)
            ->map(static fn (array $source): array => [
                'source_type' => (string) ($source['source_type'] ?? ''),
                'source_id' => (string) ($source['source_id'] ?? ''),
            ])
            ->filter(static fn (array $source): bool => $source['source_type'] !== '' && $source['source_id'] !== '')
            ->unique(static fn (array $source): string => $source['source_type'].'|'.$source['source_id'])
            ->values();

        if ($sourcePairs->isEmpty()) {
            return [];
        }

        $scores = collect($candidates)->mapWithKeys(static fn (array $source): array => [
            ((string) ($source['source_type'] ?? '')).'|'.((string) ($source['source_id'] ?? '')) => (float) ($source['score'] ?? 0.0),
        ]);

        $rows = DB::table('tenant_knowledge_embeddings')
            ->where('tenant_id', (string) $tenant->getKey())
            ->where(function ($query) use ($sourcePairs): void {
                foreach ($sourcePairs as $source) {
                    $query->orWhere(function ($inner) use ($source): void {
                        $inner->where('source_type', $source['source_type'])
                            ->where('source_id', $source['source_id']);
                    });
                }
            })
            ->get()
            ->sortByDesc(static fn (object $row): float => (float) $scores->get(((string) $row->source_type).'|'.((string) $row->source_id), 0.0))
            ->values()
            ->take($limit);

        return $rows
            ->map(fn (object $row): array => $this->rowToSource(
                $row,
                (float) $scores->get(((string) $row->source_type).'|'.((string) $row->source_id), 0.0),
                'qdrant'
            ))
            ->all();
    }

    /**
     * @param  list<float>  $queryEmbedding
     * @return list<array<string,mixed>>
     */
    public function retrieveByEmbedding(Tenant $tenant, array $queryEmbedding, int $limit): array
    {
        $rows = DB::table('tenant_knowledge_embeddings')
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereNotNull('embedding_json')
            ->limit(max($limit * 8, 24))
            ->get();

        $scored = [];
        foreach ($rows as $row) {
            $embedding = json_decode((string) $row->embedding_json, true);
            if (! is_array($embedding) || $embedding === []) {
                continue;
            }

            $scored[] = [
                'score' => $this->cosineSimilarity($queryEmbedding, array_map(static fn (mixed $value): float => (float) $value, $embedding)),
                'row' => $row,
            ];
        }

        usort($scored, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_map(
            fn (array $entry): array => $this->rowToSource($entry['row'], (float) $entry['score'], 'embedding'),
            array_slice($scored, 0, $limit)
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function retrieveLexically(Tenant $tenant, string $query, int $limit): array
    {
        $terms = collect(explode(' ', mb_strtolower($query)))
            ->map(static fn (string $term): string => trim($term))
            ->filter(static fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $rows = DB::table('tenant_knowledge_embeddings')
            ->where('tenant_id', (string) $tenant->getKey())
            ->when($terms !== [], function ($builder) use ($terms): void {
                $builder->where(function ($inner) use ($terms): void {
                    foreach ($terms as $term) {
                        $inner->orWhere('embedding_input', 'like', '%'.$term.'%')
                            ->orWhere('title', 'like', '%'.$term.'%');
                    }
                });
            })
            ->latest('indexed_at')
            ->limit(max($limit * 4, 12))
            ->get();

        $scored = [];
        foreach ($rows as $row) {
            $haystack = mb_strtolower((string) $row->title.' '.(string) $row->embedding_input);
            $score = 0.0;

            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    $score += 1.0;
                }
            }

            $scored[] = ['score' => $score, 'row' => $row];
        }

        usort($scored, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_map(
            fn (array $entry): array => $this->rowToSource($entry['row'], (float) $entry['score'], 'lexical'),
            array_slice($scored, 0, $limit)
        );
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        $dimension = min(count($left), count($right));
        if ($dimension === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        for ($i = 0; $i < $dimension; $i++) {
            $l = (float) $left[$i];
            $r = (float) $right[$i];
            $dot += $l * $r;
            $leftMagnitude += $l * $l;
            $rightMagnitude += $r * $r;
        }

        if ($leftMagnitude <= 0.0 || $rightMagnitude <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
    }

    private function rowToSource(object $row, float $score, string $retrievalMode): array
    {
        return [
            'id' => (string) $row->id,
            'source_type' => (string) $row->source_type,
            'source_id' => (string) $row->source_id,
            'title' => (string) $row->title,
            'excerpt' => $this->redactor->redactText(Str::limit((string) $row->embedding_input, 360, '...')),
            'score' => round($score, 6),
            'retrieval_mode' => $retrievalMode,
            'indexed_at' => $row->indexed_at ?? null,
        ];
    }
}
