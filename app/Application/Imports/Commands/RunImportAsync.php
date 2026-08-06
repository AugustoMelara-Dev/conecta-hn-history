<?php

declare(strict_types=1);

namespace App\Application\Imports\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

/**
 * RunImportAsync - Encola una importación para ejecutarse async.
 */
class RunImportAsync extends Command
{
    protected $signature = 'imports:run-async {entity} {file} {--tenant=}';

    protected $description = 'Queue an import for async execution';

    public function handle(): int
    {
        Queue::push(\App\Jobs\RunImportJob::class, [
            'entity' => $this->argument('entity'),
            'file' => $this->argument('file'),
            'tenant_id' => $this->option('tenant'),
        ]);
        $this->info('Import job queued.');
        return self::SUCCESS;
    }
}
