<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use App\Application\BusinessAi\Contracts\BusinessRagServiceInterface;
use App\Exceptions\InsufficientAiCreditsException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class TenantPrivateRagService implements BusinessRagServiceInterface
{
    public function __construct(
        private readonly BusinessEmbeddingClient $embeddingClient,
        private readonly PrivateBusinessQdrantClient $qdrantClient,
        private readonly PrivateRagRedactor $redactor,
        private readonly BudgetEnforcer $budgetEnforcer,
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
                $sources = $this->retrieveByVectorStore($tenant, $queryEmbedding, $safeLimit);

                if ($sources === []) {
                    $sources = $this->retrieveByEmbedding($tenant, $queryEmbedding, $safeLimit);
                }
            }
        } catch (\Throwable $exception) {
            $degradedReason = $exception->getMessage();
        }

        if ($sources === []) {
            $sources = $this->retrieveLexically($tenant, $safeQuery, $safeLimit);
            $degradedReason ??= 'lexical_retrieval';
        }

        $answer = $this->generateAnswer($tenant, $safeQuery, $sources, $degradedReason, $userId);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->audit($tenant, $userId, $safeQuery, $answer, $sources, $latencyMs, $degradedReason, 200);

        return [
            'answer' => $answer,
            'sources' => $this->sourcePayload($sources),
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

    /**
     * @param  list<float>  $queryEmbedding
     * @return list<array<string,mixed>>
     */
    private function retrieveByVectorStore(Tenant $tenant, array $queryEmbedding, int $limit): array
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
    private function retrieveByEmbedding(Tenant $tenant, array $queryEmbedding, int $limit): array
    {
        $table = 'tenant_knowledge_embeddings';

        $rows = DB::table($table)
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
    private function retrieveLexically(Tenant $tenant, string $query, int $limit): array
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
     * @param  list<array<string,mixed>>  $sources
     */
    private function generateAnswer(Tenant $tenant, string $query, array $sources, ?string $degradedReason, ?string $userId): string
    {
        if ($sources === []) {
            return 'No encontre informacion privada indexada para responder esa pregunta. Reindexa el conocimiento del negocio y vuelve a intentar.';
        }

        $apiKey = (string) config('services.openai.api_key', '');
        if (trim($apiKey) === '' || ! (bool) config('ai-rag.private.llm_enabled', true)) {
            return $this->deterministicAnswer($query, $sources, $degradedReason);
        }

        $context = collect($sources)
            ->map(static fn (array $source): string => '- '.$source['title'].': '.$source['excerpt'])
            ->implode("\n");

        $estimatedCost = max(0.000001, (float) config('ai-rag.private.estimated_chat_cost_usd', 0.002));
        $reservationId = null;
        $model = (string) config('ai-rag.private.chat_model', 'gpt-4o-mini');
        $maxTokens = max(128, (int) config('ai-rag.private.max_completion_tokens', 700));

        try {
            $reservationId = $this->budgetEnforcer->reserve(
                tenantId: (string) $tenant->getKey(),
                estimatedCost: $estimatedCost,
                operation: 'business_private_rag_chat'
            );

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(max(5, (int) config('ai-rag.private.openai_timeout_seconds', 12)))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres el copiloto privado de un negocio en Vito Business OS. Responde solo con el contexto privado entregado. Si falta informacion, dilo. No inventes datos, no reveles emails, telefonos, direcciones ni identificadores internos.',
                        ],
                        [
                            'role' => 'user',
                            'content' => "Negocio: {$tenant->name}\nPregunta: {$query}\nContexto privado redaccion segura:\n{$context}",
                        ],
                    ],
                ]);

            if ($response->ok()) {
                $answer = trim((string) $response->json('choices.0.message.content', ''));
                if ($answer !== '') {
                    $usage = (array) $response->json('usage', []);
                    $inputTokens = max(0, (int) ($usage['prompt_tokens'] ?? 0));
                    $outputTokens = max(0, (int) ($usage['completion_tokens'] ?? 0));
                    $actualCost = $this->calculateChatCost($inputTokens, $outputTokens);

                    $this->budgetEnforcer->settle(
                        tenantId: (string) $tenant->getKey(),
                        estimatedCost: $estimatedCost,
                        actualCost: $actualCost,
                        reservationId: $reservationId,
                        usageMetadata: [
                            'model' => $model,
                            'input_tokens' => $inputTokens,
                            'output_tokens' => $outputTokens,
                            'operation' => 'business_private_rag_chat',
                        ]
                    );

                    $this->logAiUsage($tenant, $userId, $model, $inputTokens, $outputTokens, $actualCost);

                    return $this->redactSensitive($answer);
                }
            }
        } catch (InsufficientAiCreditsException $exception) {
            Log::warning('business_rag.llm_budget_insufficient', [
                'tenant_id' => (string) $tenant->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return $this->deterministicAnswer($query, $sources, 'ai_budget_insufficient');
        } catch (\Throwable $exception) {
            if (is_string($reservationId)) {
                $this->budgetEnforcer->refund(
                    tenantId: (string) $tenant->getKey(),
                    estimatedCost: $estimatedCost,
                    reservationId: $reservationId,
                    failureReason: $exception->getMessage()
                );
            }

            Log::warning('business_rag.llm_failed', [
                'tenant_id' => (string) $tenant->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        if (is_string($reservationId)) {
            $this->budgetEnforcer->refund(
                tenantId: (string) $tenant->getKey(),
                estimatedCost: $estimatedCost,
                reservationId: $reservationId,
                failureReason: 'business_rag_llm_empty_or_error'
            );
        }

        return $this->deterministicAnswer($query, $sources, $degradedReason ?? 'llm_unavailable');
    }

    private function calculateChatCost(int $inputTokens, int $outputTokens): float
    {
        $inputPerMillion = (float) config('ai-rag.private.chat_input_cost_per_1m_tokens', 0.15);
        $outputPerMillion = (float) config('ai-rag.private.chat_output_cost_per_1m_tokens', 0.60);

        return round(($inputTokens / 1_000_000 * $inputPerMillion) + ($outputTokens / 1_000_000 * $outputPerMillion), 6);
    }

    private function logAiUsage(Tenant $tenant, ?string $userId, string $model, int $inputTokens, int $outputTokens, float $actualCost): void
    {
        if ($userId === null || ! Schema::hasTable('ai_usage_logs')) {
            return;
        }

        DB::table('ai_usage_logs')->insert([
            'user_id' => $userId,
            'tenant_id' => (string) $tenant->getKey(),
            'provider' => 'openai',
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $actualCost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string,mixed>>  $sources
     */
    private function deterministicAnswer(string $query, array $sources, ?string $degradedReason): string
    {
        $head = array_slice($sources, 0, 3);
        $lines = array_map(static fn (array $source): string => sprintf('%s: %s', $source['title'], $source['excerpt']), $head);
        $suffix = $degradedReason ? "\n\nNota operativa: respuesta generada en modo {$degradedReason}." : '';

        return "Segun los datos privados del negocio para: {$query}\n".implode("\n", $lines).$suffix;
    }

    private function rowToSource(object $row, float $score, string $retrievalMode): array
    {
        return [
            'id' => (string) $row->id,
            'source_type' => (string) $row->source_type,
            'source_id' => (string) $row->source_id,
            'title' => (string) $row->title,
            'excerpt' => $this->redactSensitive(Str::limit((string) $row->embedding_input, 360, '...')),
            'score' => round($score, 6),
            'retrieval_mode' => $retrievalMode,
            'indexed_at' => $row->indexed_at ?? null,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $sources
     * @return list<array<string,mixed>>
     */
    private function sourcePayload(array $sources): array
    {
        return array_map(static fn (array $source): array => [
            'source_type' => $source['source_type'],
            'source_id' => $source['source_id'],
            'title' => $source['title'],
            'score' => $source['score'],
            'retrieval_mode' => $source['retrieval_mode'],
            'excerpt' => $source['excerpt'],
        ], $sources);
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

    private function normalizeQuery(string $query): string
    {
        $query = $this->redactor->redactText($query);
        $query = preg_replace('/\s+/', ' ', $query) ?? $query;

        return trim(mb_substr($query, 0, (int) config('ai-rag.private.max_query_chars', 500)));
    }

    private function redactSensitive(string $value): string
    {
        return $this->redactor->redactText($value);
    }

    /**
     * @param  list<array<string,mixed>>  $sources
     */
    private function audit(Tenant $tenant, ?string $userId, string $query, string $answer, array $sources, int $latencyMs, ?string $degradedReason, int $statusCode): void
    {
        if (! Schema::hasTable('business_ai_rag_audits')) {
            return;
        }

        DB::table('business_ai_rag_audits')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => $userId,
            'query_hash' => hash('sha256', $query),
            'query_excerpt' => Str::limit($this->redactSensitive($query), 180, '...'),
            'answer_excerpt' => Str::limit($this->redactSensitive($answer), 300, '...'),
            'source_count' => count($sources),
            'latency_ms' => $latencyMs,
            'degraded_reason' => $degradedReason,
            'status_code' => $statusCode,
            'metadata' => json_encode([
                'scope' => 'tenant_private',
                'source_types' => array_values(array_unique(array_map(static fn (array $source): string => (string) $source['source_type'], $sources))),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
