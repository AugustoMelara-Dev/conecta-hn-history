<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dynamic\DynamicRecord;
use App\Models\Dynamic\EntityDefinition;
use App\Services\DynamicForms\RecordPersistenceService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class DynamicFormsReindexCommand extends Command
{
    protected $signature = 'dynamic-forms:reindex
        {entity : EntityDefinition UUID}
        {--tenant= : Optional tenant UUID filter}
        {--chunk=200 : Records per chunk}
        {--dry-run : Only estimate records, do not mutate indexes}
        {--json : Emit JSON output}';

    protected $description = 'Rebuild dynamic record index values for a specific entity definition.';

    public function handle(RecordPersistenceService $recordPersistence): int
    {
        if (! Schema::hasTable('dynamic_records') || ! Schema::hasTable('dynamic_record_index_values')) {
            return $this->emitFailure('Required dynamic record tables are missing.');
        }

        $entityId = (string) $this->argument('entity');
        $tenantId = $this->normalizeString($this->option('tenant'));
        $chunkSize = max(10, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $entity = EntityDefinition::query()->with('fields')->find($entityId);
        if (! $entity instanceof EntityDefinition) {
            return $this->emitFailure('Entity definition not found.');
        }

        $query = DynamicRecord::query()
            ->where('entity_definition_id', $entity->getKey())
            ->orderBy('id');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $total = (clone $query)->count();
        $processed = 0;
        $failed = 0;
        $errors = [];

        if (! $dryRun) {
            $query->chunk($chunkSize, function ($records) use (&$processed, &$failed, &$errors, $recordPersistence, $entity): void {
                /** @var DynamicRecord $record */
                foreach ($records as $record) {
                    try {
                        $recordPersistence->resyncIndexesForRecord($entity, $record);
                        $processed++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        $errors[] = sprintf('%s: %s', $record->getKey(), $exception->getMessage());
                    }
                }
            });
        }

        $summary = [
            'entity_id' => (string) $entity->getKey(),
            'tenant_filter' => $tenantId,
            'dry_run' => $dryRun,
            'total_records' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'chunk' => $chunkSize,
            'timestamp' => now()->toIso8601String(),
        ];

        if ((bool) $this->option('json')) {
            if ($errors !== []) {
                $summary['errors'] = array_slice($errors, 0, 20);
            }

            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('Dynamic reindex entity=%s dry_run=%s', $entity->getKey(), $dryRun ? 'true' : 'false'));
            $this->line(sprintf('- total=%d processed=%d failed=%d chunk=%d', $total, $processed, $failed, $chunkSize));

            if ($errors !== []) {
                foreach (array_slice($errors, 0, 5) as $error) {
                    $this->warn('ERROR: '.$error);
                }
            }
        }

        if ($failed > 0) {
            return self::FAILURE;
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
