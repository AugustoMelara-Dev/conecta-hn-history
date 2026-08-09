<?php

declare(strict_types=1);

namespace App\Application\Imports\Commands;

use App\Application\Imports\Exports\TemplateExport;
use App\Application\Imports\ImportService;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class GenerateImportTemplate extends Command
{
    protected $signature = 'imports:generate-template {entity} {--output=}';

    protected $description = 'Generate a CSV template for a specific entity';

    public function handle(ImportService $importService): int
    {
        $entity = $this->argument('entity');
        if (! $importService->has($entity)) {
            $this->error("Unknown entity: {$entity}");

            return self::FAILURE;
        }

        $templates = $importService->templates();
        $template = $templates[$entity] ?? null;

        if (! $template) {
            $this->error("No template available for: {$entity}");

            return self::FAILURE;
        }

        $output = $this->option('output') ?? storage_path("app/templates/{$entity}.xlsx");
        Excel::store(
            new TemplateExport($template['headers'], $template['sample']),
            basename($output),
            'local'
        );
        $this->info("Template generated: {$output}");

        return self::SUCCESS;
    }
}
