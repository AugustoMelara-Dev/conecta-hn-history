<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Knowledge;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Pgvector\Vector;

final class EloquentKnowledgeIndexRepository
{
    /**
     * @param  list<string>  $resourceKeys
     */
    public function pruneTenant(string $tenantId, array $resourceKeys): void
    {
        if (! Str::isUuid($tenantId)) {
            throw new InvalidArgumentException('Invalid tenant knowledge scope.');
        }

        $retained = array_fill_keys($resourceKeys, true);

        DB::table('ai_knowledge_chunks')
            ->where('tenant_id', $tenantId)
            ->whereIn('resource_type', ['product', 'service', 'business'])
            ->select(['id', 'resource_type', 'resource_id'])
            ->get()
            ->each(function (object $row) use ($retained): void {
                $key = (string) $row->resource_type.':'.(string) $row->resource_id;

                if (! isset($retained[$key])) {
                    DB::table('ai_knowledge_chunks')->where('id', (string) $row->id)->delete();
                }
            });
    }

    /**
     * @param  list<float|int>  $embedding
     * @param  array<string, mixed>  $metadata
     */
    public function upsert(
        string $tenantId,
        string $resourceType,
        string $resourceId,
        string $visibility,
        string $content,
        array $embedding,
        string $embeddingModel,
        array $metadata = [],
    ): void {
        $this->validate($tenantId, $resourceType, $resourceId, $visibility, $content, $embedding, $embeddingModel);
        $now = now();

        DB::table('ai_knowledge_chunks')->upsert([[
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'visibility' => $visibility,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'embedding' => $this->serializedEmbedding($embedding),
            'embedding_model' => $embeddingModel,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'indexed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['tenant_id', 'resource_type', 'resource_id'], [
            'visibility',
            'content',
            'content_hash',
            'embedding',
            'embedding_model',
            'metadata',
            'indexed_at',
            'updated_at',
        ]);
    }

    /**
     * @param  list<float|int>  $embedding
     * @return list<array<string, mixed>>
     */
    public function search(array $embedding, ?string $tenantId, bool $includePublic, int $limit): array
    {
        $this->validateEmbedding($embedding);

        if ($limit < 1 || $limit > 50 || (! $includePublic && $tenantId === null)) {
            throw new InvalidArgumentException('Invalid knowledge search scope.');
        }

        return DB::getDriverName() === 'pgsql'
            ? $this->postgresSearch($embedding, $tenantId, $includePublic, $limit)
            : $this->portableSearch($embedding, $tenantId, $includePublic, $limit);
    }

    /**
     * @param  list<float|int>  $embedding
     * @return list<array<string, mixed>>
     */
    private function postgresSearch(array $embedding, ?string $tenantId, bool $includePublic, int $limit): array
    {
        $vector = (string) new Vector(array_map(static fn (float|int $value): float => (float) $value, $embedding));
        $query = $this->scopedQuery($tenantId, $includePublic)
            ->select([
                'id',
                'tenant_id',
                'resource_type',
                'resource_id',
                'visibility',
                'content',
                'content_hash',
                'embedding_model',
                'metadata',
                'indexed_at',
            ])
            ->selectRaw('1 - (embedding <=> ?::vector) AS similarity', [$vector])
            ->orderByRaw('embedding <=> ?::vector', [$vector])
            ->limit($limit)
            ->get();

        return $query->map(fn (object $row): array => $this->normalizeRow($row))->values()->all();
    }

    /**
     * @param  list<float|int>  $embedding
     * @return list<array<string, mixed>>
     */
    private function portableSearch(array $embedding, ?string $tenantId, bool $includePublic, int $limit): array
    {
        return $this->scopedQuery($tenantId, $includePublic)
            ->get()
            ->map(function (object $row) use ($embedding): array {
                $candidate = json_decode((string) $row->embedding, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($candidate)) {
                    throw new InvalidArgumentException('Stored embedding is invalid.');
                }

                $normalized = $this->normalizeRow($row);
                $normalized['similarity'] = $this->cosine($embedding, $candidate);

                return $normalized;
            })
            ->sortByDesc('similarity')
            ->take($limit)
            ->values()
            ->all();
    }

    private function scopedQuery(?string $tenantId, bool $includePublic): Builder
    {
        return DB::table('ai_knowledge_chunks')
            ->where(function ($scope) use ($tenantId, $includePublic): void {
                if ($tenantId !== null) {
                    $scope->where('tenant_id', $tenantId)
                        ->whereIn('visibility', $includePublic ? ['public', 'private'] : ['private']);

                    return;
                }

                $scope->where('visibility', 'public');
            });
    }

    /** @return array<string, mixed> */
    private function normalizeRow(object $row): array
    {
        $metadata = json_decode((string) ($row->metadata ?? '{}'), true);

        return [
            'id' => (string) $row->id,
            'tenant_id' => (string) $row->tenant_id,
            'resource_type' => (string) $row->resource_type,
            'resource_id' => (string) $row->resource_id,
            'visibility' => (string) $row->visibility,
            'content' => (string) $row->content,
            'content_hash' => (string) $row->content_hash,
            'embedding_model' => (string) $row->embedding_model,
            'metadata' => is_array($metadata) ? $metadata : [],
            'indexed_at' => (string) $row->indexed_at,
            'similarity' => isset($row->similarity) ? (float) $row->similarity : 0.0,
        ];
    }

    /** @param list<float|int> $embedding */
    private function serializedEmbedding(array $embedding): string
    {
        $values = array_map(static fn (float|int $value): float => (float) $value, $embedding);

        return DB::getDriverName() === 'pgsql'
            ? (string) new Vector($values)
            : json_encode($values, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<float|int>  $left
     * @param  list<mixed>  $right
     */
    private function cosine(array $left, array $right): float
    {
        if (count($left) !== count($right)) {
            throw new InvalidArgumentException('Embedding dimensions do not match.');
        }

        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;
        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index] ?? null;
            if (! is_numeric($rightValue)) {
                throw new InvalidArgumentException('Stored embedding contains a non-numeric value.');
            }

            $a = (float) $leftValue;
            $b = (float) $rightValue;
            $dot += $a * $b;
            $leftNorm += $a * $a;
            $rightNorm += $b * $b;
        }

        if ($leftNorm === 0.0 || $rightNorm === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }

    /**
     * @param  list<float|int>  $embedding
     */
    private function validate(
        string $tenantId,
        string $resourceType,
        string $resourceId,
        string $visibility,
        string $content,
        array $embedding,
        string $embeddingModel,
    ): void {
        if (! Str::isUuid($tenantId)
            || ! in_array($resourceType, ['product', 'service', 'business', 'policy'], true)
            || trim($resourceId) === '' || mb_strlen($resourceId) > 64
            || ! in_array($visibility, ['public', 'private'], true)
            || trim($content) === '' || mb_strlen($content) > 8000
            || trim($embeddingModel) === '' || mb_strlen($embeddingModel) > 100) {
            throw new InvalidArgumentException('Invalid knowledge chunk.');
        }

        $this->validateEmbedding($embedding);
    }

    /** @param list<mixed> $embedding */
    private function validateEmbedding(array $embedding): void
    {
        $dimensions = (int) config('ai.knowledge.embedding_dimensions', 1536);
        if (count($embedding) !== $dimensions) {
            throw new InvalidArgumentException('Embedding dimensions do not match the configured model.');
        }

        foreach ($embedding as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Embedding contains an invalid value.');
            }
        }
    }
}
