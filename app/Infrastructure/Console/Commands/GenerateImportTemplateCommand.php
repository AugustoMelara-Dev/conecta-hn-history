<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Imports\Exports\TemplateExport;
use App\Application\Imports\OperationalImportService;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

final class GenerateImportTemplateCommand extends Command
{
    protected $signature = 'imports:generate-template {kind} {--output=}';

    protected $description = 'Generate a CSV template for a specific entity';

    public function handle(OperationalImportService $imports): int
    {
        $kind = (string) $this->argument('kind');
        if (! array_key_exists($kind, $imports->kinds())) {
            $this->error("Unknown operational import kind: {$kind}");

            return self::FAILURE;
        }

        $headers = $imports->columns($kind);
        $sample = array_fill_keys($headers, '');
        $output = $this->option('output') ?? "templates/{$kind}.xlsx";
        Excel::store(
            new TemplateExport($headers, $sample),
            (string) $output,
            'local'
        );
        $this->info("Template generated: {$output}");

        return self::SUCCESS;
    }
}
