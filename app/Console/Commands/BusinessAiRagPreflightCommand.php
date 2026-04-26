<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class BusinessAiRagPreflightCommand extends Command
{
    protected $signature = 'business-ai:rag-preflight
        {--strict : Exit with code 1 when required checks fail}
        {--require-qdrant : Require the private tenant RAG vector store to use Qdrant}
        {--json : Emit JSON output}';

    protected $description = 'Validate private business RAG runtime isolation and vector store configuration.';

    public function handle(): int
    {
        $privateCollection = (string) config('ai-rag.scopes.tenant_private.collection', '');
        $publicCollection = (string) config('ai-rag.scopes.directory_public.collection', '');
        $driver = (string) config('ai-rag.private.vector_store.driver', 'sql');
        $qdrantEnabled = (bool) config('ai-rag.private.qdrant.enabled', false);

        $checks = [
            'OPENAI_API_KEY' => $this->checkNotBlank((string) config('services.openai.api_key', '')),
            'TENANT_PRIVATE_SQL_TABLE' => Schema::hasTable((string) config('ai-rag.scopes.tenant_private.sql_table', 'tenant_knowledge_embeddings')),
            'BUSINESS_AI_AUDIT_TABLE' => Schema::hasTable('business_ai_rag_audits'),
            'TENANT_PRIVATE_COLLECTION' => $this->checkNotBlank($privateCollection),
            'DIRECTORY_PUBLIC_COLLECTION' => $this->checkNotBlank($publicCollection),
            'COLLECTIONS_ARE_ISOLATED' => $privateCollection !== '' && $publicCollection !== '' && $privateCollection !== $publicCollection,
            'PRIVATE_VECTOR_DRIVER' => in_array($driver, ['sql', 'qdrant'], true),
            'PRIVATE_RAG_SCOPE_PAYLOAD' => $this->hasRequiredPayloadContract(),
            'PRIVATE_RATE_LIMITS' => (int) config('ai-rag.private.rate_limits.per_user_minute', 0) >= 1
                && (int) config('ai-rag.private.rate_limits.per_tenant_minute', 0) >= 1,
        ];

        if ((bool) $this->option('require-qdrant')) {
            $checks['TENANT_PRIVATE_VECTOR_DRIVER_IS_QDRANT'] = $driver === 'qdrant';
            $checks['TENANT_PRIVATE_QDRANT_ENABLED'] = $qdrantEnabled;
        }

        if ($driver === 'qdrant' || $qdrantEnabled || (bool) $this->option('require-qdrant')) {
            $checks['TENANT_PRIVATE_QDRANT_ENABLED'] = $qdrantEnabled;
            $checks['TENANT_PRIVATE_QDRANT_URL'] = $this->checkUrl((string) config('ai-rag.private.qdrant.url', ''));
            $checks['TENANT_PRIVATE_QDRANT_TIMEOUT'] = (int) config('ai-rag.private.qdrant.timeout_seconds', 0) >= 2;
            $checks['TENANT_PRIVATE_QDRANT_MAX_CANDIDATES'] = (int) config('ai-rag.private.qdrant.max_candidates', 0) >= 1;
        }

        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => ! $ok));
        $summary = [
            'status' => $failed === [] ? 'ok' : 'failed',
            'failed_checks' => $failed,
            'checks' => $checks,
            'runtime' => [
                'private_collection' => $privateCollection,
                'public_collection' => $publicCollection,
                'vector_driver' => $driver,
                'qdrant_enabled' => $qdrantEnabled,
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $name => $ok) {
                $this->line(sprintf('%s %s', $ok ? 'OK   ' : 'FAIL ', $name));
            }
        }

        if ($failed !== [] && (bool) $this->option('strict')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function hasRequiredPayloadContract(): bool
    {
        $required = array_map('strval', (array) config('ai-rag.scopes.tenant_private.required_payload', []));

        foreach (['tenant_id', 'rag_scope', 'source_type', 'source_id', 'visibility', 'redaction_version'] as $key) {
            if (! in_array($key, $required, true)) {
                return false;
            }
        }

        return true;
    }

    private function checkNotBlank(string $value): bool
    {
        return trim($value) !== '';
    }

    private function checkUrl(string $value): bool
    {
        if (trim($value) === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
