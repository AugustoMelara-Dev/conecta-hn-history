<?php

declare(strict_types=1);

namespace App\Application\Imports\Commands;

use App\Application\Imports\OperationalImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * RunImportAsync - Encola una importación para ejecutarse async.
 */
class RunImportAsync extends Command
{
    protected $signature = 'imports:run-async {kind} {file} {--tenant=} {--actor=} {--idempotency=}';

    protected $description = 'Queue an import for async execution';

    public function handle(OperationalImportService $imports): int
    {
        $tenantId = (string) $this->option('tenant');
        $actorId = (string) $this->option('actor');
        $path = $this->absolutePath((string) $this->argument('file'));
        if ($tenantId === '' || $actorId === '' || ! is_file($path)) {
            $this->error('A valid file, --tenant and --actor are required.');

            return self::FAILURE;
        }

        $batchId = $imports->stage(
            tenantId: $tenantId,
            actorId: $actorId,
            file: new UploadedFile($path, basename($path), null, null, true),
            kind: (string) $this->argument('kind'),
            idempotencyKey: (string) ($this->option('idempotency') ?: Str::uuid()),
        );
        $this->info("Operational import {$batchId} queued.");

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
            ? $path
            : storage_path('app/'.$path);
    }
}
