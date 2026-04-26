<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dynamic\DynamicReindexRun;
use App\Services\DynamicForms\DynamicReindexPipelineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class DynamicFormsReindexResumeCommand extends Command
{
    protected $signature = 'dynamic-forms:reindex-resume
        {run : Dynamic reindex run UUID}
        {--json : Emit JSON output}';

    protected $description = 'Resume a failed/interrupted dynamic async reindex run from last checkpoint.';

    public function handle(DynamicReindexPipelineService $pipeline): int
    {
        foreach (['dynamic_reindex_runs', 'dynamic_reindex_checkpoints'] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->emitFailure("Missing required table: {$table}");
            }
        }

        $runId = (string) $this->argument('run');
        $run = DynamicReindexRun::query()->find($runId);
        if (! $run instanceof DynamicReindexRun) {
            return $this->emitFailure('Reindex run not found.');
        }

        if ($run->status === 'completed') {
            return $this->emitFailure('Reindex run is already completed.');
        }

        $run = $pipeline->resumeRun($run);

        $payload = [
            'run_id' => (string) $run->getKey(),
            'status' => (string) $run->status,
            'last_record_id' => $run->last_record_id,
            'timestamp' => now()->toIso8601String(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf('Dynamic reindex resumed run=%s status=%s', $payload['run_id'], $payload['status']));
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

