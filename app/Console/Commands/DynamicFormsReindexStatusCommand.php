<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dynamic\DynamicReindexRun;
use App\Services\DynamicForms\DynamicReindexPipelineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class DynamicFormsReindexStatusCommand extends Command
{
    protected $signature = 'dynamic-forms:reindex-status
        {run : Dynamic reindex run UUID}
        {--limit=20 : Number of checkpoints to include}
        {--json : Emit JSON output}';

    protected $description = 'Inspect dynamic async reindex run status, checkpoints and failures.';

    public function handle(DynamicReindexPipelineService $pipeline): int
    {
        foreach (['dynamic_reindex_runs', 'dynamic_reindex_checkpoints', 'dynamic_reindex_failures'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->emitFailure("Missing required table: {$table}");
            }
        }

        $runId = (string) $this->argument('run');
        $run = DynamicReindexRun::query()->find($runId);
        if (! $run instanceof DynamicReindexRun) {
            return $this->emitFailure('Reindex run not found.');
        }

        $report = $pipeline->statusPayload($run, max(1, (int) $this->option('limit')));

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $runData = (array) ($report['run'] ?? []);
            $this->line(sprintf('Dynamic reindex run=%s status=%s', (string) ($runData['id'] ?? ''), (string) ($runData['status'] ?? 'unknown')));
            $this->line(sprintf(
                '- processed=%d/%d failed=%d chunk=%d',
                (int) ($runData['processed_records'] ?? 0),
                (int) ($runData['total_records'] ?? 0),
                (int) ($runData['failed_records'] ?? 0),
                (int) ($runData['chunk_size'] ?? 0),
            ));
            $this->line(sprintf('- checkpoints=%d failures=%d', count((array) ($report['checkpoints'] ?? [])), (int) ($report['failure_count'] ?? 0)));
        }

        return self::SUCCESS;
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

