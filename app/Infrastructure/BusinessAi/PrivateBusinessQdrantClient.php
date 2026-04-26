<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PrivateBusinessQdrantClient
{
    public function enabled(): bool
    {
        return (string) config('ai-rag.private.vector_store.driver', 'sql') === 'qdrant'
            && (bool) config('ai-rag.private.qdrant.enabled', false);
    }

    /**
     * @param  list<float>  $embedding
     * @param  array<string, mixed>  $payload
     */
    public function upsertKnowledgePoint(Tenant $tenant, string $pointId, array $embedding, array $payload): void
    {
        $this->upsertKnowledgePoints($tenant, [[
            'id' => $pointId,
            'embedding' => $embedding,
            'payload' => $payload,
        ]]);
    }

    /**
     * @param  list<array{id:string,embedding:list<float>,payload:array<string,mixed>}>  $points
     */
    public function upsertKnowledgePoints(Tenant $tenant, array $points): void
    {
        if (! $this->enabled() || $points === []) {
            return;
        }

        $payloadPoints = [];
        foreach ($points as $point) {
            $embedding = $point['embedding'];
            if ($embedding === []) {
                continue;
            }

            $this->assertVectorDimension($embedding);

            $safePayload = array_merge($point['payload'], [
                'tenant_id' => (string) $tenant->getKey(),
                'rag_scope' => 'tenant_private',
                'visibility' => 'private',
                'redaction_version' => (string) config('ai-rag.redaction.version', 'private-redaction-v1'),
            ]);

            foreach ((array) config('ai-rag.scopes.tenant_private.required_payload', []) as $requiredKey) {
                if (! array_key_exists((string) $requiredKey, $safePayload)) {
                    throw new RuntimeException('business_qdrant_payload_missing_'.$requiredKey);
                }
            }

            $payloadPoints[] = [
                'id' => $point['id'],
                'vector' => array_values($embedding),
                'payload' => $safePayload,
            ];
        }

        if ($payloadPoints === []) {
            return;
        }

        $wait = (bool) config('ai-rag.private.qdrant.wait_for_upsert', false) ? 'true' : 'false';
        $response = $this->request()->put(
            $this->collectionEndpoint('/points?wait='.$wait),
            [
                'points' => $payloadPoints,
            ]
        );

        if (! $response->ok()) {
            $this->throwFailure('business_qdrant_upsert_failed', $response->status(), (string) $response->body());
        }
    }

    /**
     * @param  list<float>  $queryVector
     * @return list<array<string, mixed>>
     */
    public function searchTenantSources(Tenant $tenant, array $queryVector, int $limit): array
    {
        if (! $this->enabled() || $queryVector === []) {
            return [];
        }

        $this->assertVectorDimension($queryVector);

        $response = $this->request()->post(
            $this->collectionEndpoint('/points/search'),
            [
                'vector' => array_values($queryVector),
                'limit' => max(1, min($limit, (int) config('ai-rag.private.qdrant.max_candidates', 24))),
                'with_payload' => true,
                'with_vector' => false,
                'filter' => [
                    'must' => [
                        ['key' => 'tenant_id', 'match' => ['value' => (string) $tenant->getKey()]],
                        ['key' => 'rag_scope', 'match' => ['value' => 'tenant_private']],
                        ['key' => 'visibility', 'match' => ['value' => 'private']],
                    ],
                ],
            ]
        );

        if (! $response->ok()) {
            $this->throwFailure('business_qdrant_search_failed', $response->status(), (string) $response->body());
        }

        $result = $response->json('result');
        if (! is_array($result)) {
            return [];
        }

        $sources = [];
        foreach ($result as $item) {
            if (! is_array($item)) {
                continue;
            }

            $payload = data_get($item, 'payload');
            if (! is_array($payload)) {
                continue;
            }

            if ((string) ($payload['tenant_id'] ?? '') !== (string) $tenant->getKey()) {
                continue;
            }

            $sources[] = [
                'id' => (string) ($item['id'] ?? ''),
                'source_type' => (string) ($payload['source_type'] ?? 'unknown'),
                'source_id' => (string) ($payload['source_id'] ?? ''),
                'title' => (string) ($payload['title'] ?? 'Fuente privada'),
                'excerpt' => (string) ($payload['excerpt'] ?? ''),
                'score' => round((float) ($item['score'] ?? 0.0), 6),
                'retrieval_mode' => 'qdrant',
                'indexed_at' => $payload['indexed_at'] ?? null,
            ];
        }

        return $sources;
    }

    private function collectionEndpoint(string $path = ''): string
    {
        $baseUrl = rtrim((string) config('ai-rag.private.qdrant.url', ''), '/');
        $collection = (string) config('ai-rag.scopes.tenant_private.collection', 'tenant_private_documents');

        return $baseUrl.'/collections/'.$collection.$path;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = (string) config('ai-rag.private.qdrant.api_key', '');
        $timeout = max(2, (int) config('ai-rag.private.qdrant.timeout_seconds', 6));
        $request = Http::acceptJson()->timeout($timeout);

        if (trim($apiKey) !== '') {
            $request = $request->withHeaders(['api-key' => $apiKey]);
        }

        return $request;
    }

    /**
     * @param  list<float>  $vector
     */
    private function assertVectorDimension(array $vector): void
    {
        $expected = (int) config('ai-rag.embedding_dimension', 1536);

        if ($expected > 0 && count($vector) !== $expected) {
            throw new RuntimeException('business_qdrant_vector_dimension_mismatch_expected_'.$expected.'_got_'.count($vector));
        }
    }

    private function throwFailure(string $prefix, int $status, string $body): never
    {
        $error = trim($body);
        $suffix = $error !== '' ? ': '.$error : '';

        throw new RuntimeException($prefix.'_'.$status.$suffix);
    }
}
