<?php

declare(strict_types=1);

namespace App\Application\Imports\Commands;

use App\Models\ImportFailure;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class RetryFailedImport extends Command
{
    protected $signature = 'imports:retry {originalJobId}';

    protected $description = 'Retry a failed import with its previous configuration';

    public function handle(): int
    {
        $jobId = $this->argument('originalJobId');
        $failures = ImportFailure::where('import_job_id', $jobId)->get();
        if ($failures->isEmpty()) {
            $this->info('No failures to retry.');
            return self::SUCCESS;
        }
        $this->info("Found {$failures->count()} failed rows.");
        // In production: regenerate file from failures and re-run.
        return self::SUCCESS;
    }
}
