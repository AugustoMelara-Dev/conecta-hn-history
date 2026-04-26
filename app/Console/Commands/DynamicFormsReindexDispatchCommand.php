<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dynamic\EntityDefinition;
use App\Services\DynamicForms\DynamicReindexPipelineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class DynamicFormsReindexDispatchCommand extends Command
{
    protected $signature = 'dynamic-forms:reindex-dispatch
        {entity : EntityDefinition UUID}
        {--tenant= : Optional tenant UUID filter}
        {--chunk=200 : Records per chunk}
        {--json : Emit JSON output}';

    protected $description = 'Dispatch async dynamic reindex run with checkpoint/retry pipeline.';

    public function handle(DynamicReindexPipelineService $pipeline): int
    {
        foreach (['dynamic_records', 'dynamic_record_index_values', 'dynamic_reindex_runs', 'dynamic_reindex_checkpoints', 'dynamic_reindex_failures'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->emitFailure("Missing required table: {$table}");
            }
        }

        $entityId = (string) $this->argument('entity');
        $tenantId = $this->normalizeString($this->option('tenant'));
        $chunk = max(10, (int) $this->option('chunk'));

        $entity = EntityDefinition::query()->find($entityId);
        if (! $entity instanceof EntityDefinition) {
            return $this->emitFailure('Entity definition not found.');
        }

        $run = $pipeline->dispatchRun(
            entity: $entity,
            tenantId: $tenantId,
            chunkSize: $chunk,
            actorId: $this->userId(),
        );

        $payload = [
            'run_id' => (string) $run->getKey(),
            'status' => (string) $run->status,
            'entity_definition_id' => (string) $run->entity_definition_id,
            'tenant_filter' => $run->tenant_id,
            'chunk_size' => (int) $run->chunk_size,
            'total_records' => (int) $run->total_records,
            'timestamp' => now()->toIso8601String(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('Dynamic reindex dispatched run=%s status=%s', $payload['run_id'], $payload['status']));
            $this->line(sprintf('- entity=%s tenant=%s chunk=%d total=%d', $entityId, (string) ($run->tenant_id ?? 'all'), $chunk, (int) $run->total_records));
        }

        return self::SUCCESS;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function userId(): ?string
    {
        $user = $this->laravel->bound('auth') ? auth()->user() : null;

        return $user?->getKey() ? (string) $user->getKey() : null;
    }

    private function emitFailure(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'status' => 'failed',
                'error' => $message,
                'timestamp' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}

