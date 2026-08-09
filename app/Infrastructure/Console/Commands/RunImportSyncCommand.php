<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Imports\OperationalImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * RunImportSync - Ejecuta una importación de manera síncrona.
 */
final class RunImportSyncCommand extends Command
{
    protected $signature = 'imports:run-sync {kind} {file} {--tenant=} {--actor=} {--idempotency=}';

    protected $description = 'Run an import synchronously';

    public function handle(OperationalImportService $imports): int
    {
        $tenantId = (string) $this->option('tenant');
        $actorId = (string) $this->option('actor');
        $file = (string) $this->argument('file');
        $path = str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $file)
            ? $file
            : storage_path('app/'.$file);
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
            dispatch: false,
        );
        $report = $imports->process($tenantId, $batchId);
        $this->info("Operational import {$batchId} processed: {$report->total} row(s), {$report->failed} failed.");

        return $report->failed === $report->total && $report->total > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
