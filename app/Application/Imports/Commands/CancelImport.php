<?php

declare(strict_types=1);

namespace App\Application\Imports\Commands;

use App\Models\ImportJob;
use Illuminate\Console\Command;

class CancelImport extends Command
{
    protected $signature = 'imports:cancel {jobId}';

    protected $description = 'Cancel an in-progress import job';

    public function handle(): int
    {
        $job = ImportJob::find($this->argument('jobId'));
        if (! $job) {
            $this->error('Job not found.');
            return self::FAILURE;
        }
        $job->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->info('Import cancelled.');
        return self::SUCCESS;
    }
}
