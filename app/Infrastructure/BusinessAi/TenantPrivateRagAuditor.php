<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class TenantPrivateRagAuditor
{
    public function __construct(
        private readonly PrivateRagRedactor $redactor,
    ) {}

    /**
     * @param  list<array<string,mixed>>  $sources
     */
    public function audit(Tenant $tenant, ?string $userId, string $query, string $answer, array $sources, int $latencyMs, ?string $degradedReason, int $statusCode): void
    {
        if (! Schema::hasTable('business_ai_rag_audits')) {
            return;
        }

        DB::table('business_ai_rag_audits')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => $userId,
            'query_hash' => hash('sha256', $query),
            'query_excerpt' => Str::limit($this->redactor->redactText($query), 180, '...'),
            'answer_excerpt' => Str::limit($this->redactor->redactText($answer), 300, '...'),
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

    /**
     * @param  list<array<string,mixed>>  $sources
     * @return list<array<string,mixed>>
     */
    public function sourcePayload(array $sources): array
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
}
