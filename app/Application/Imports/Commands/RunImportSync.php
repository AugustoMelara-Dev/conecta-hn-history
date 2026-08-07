<?php

declare(strict_types=1);

namespace App\Application\Imports\Commands;

use App\Application\Imports\ImportService;
use App\Infrastructure\Imports\ExcelImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * RunImportSync - Ejecuta una importación de manera síncrona.
 */
class RunImportSync extends Command
{
    protected $signature = 'imports:run-sync {entity} {file} {--tenant=}';

    protected $description = 'Run an import synchronously';

    public function handle(ImportService $importService, ExcelImporter $importer): int
    {
        $entity = $this->argument('entity');
        $file = $this->argument('file');
        $tenantId = $this->option('tenant') ?? auth()->user()?->tenant_id ?? '1';

        if (! $importService->has($entity)) {
            $this->error("Unknown entity: {$entity}");
            return self::FAILURE;
        }

        $path = Storage::disk('local')->path($file);
        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $importClass = $importService->getImporterClass($entity);
        $result = $importer->import($importClass, $path);

        if ($result['success']) {
            $this->info("Imported {$result['imported']} rows in {$result['duration_ms']}ms");
            return self::SUCCESS;
        }

        $this->error('Errors: ' . implode(', ', $result['errors']));
        return self::FAILURE;
    }
}
