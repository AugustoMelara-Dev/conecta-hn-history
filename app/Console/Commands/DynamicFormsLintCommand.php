<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\DynamicForms\Contracts\DynamicSchemaLinterInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class DynamicFormsLintCommand extends Command
{
    protected $signature = 'dynamic-forms:lint
        {--strict : Fail command when lint errors are detected}
        {--changed-only : Lint only recently changed entity/template definitions}
        {--json : Emit JSON output}';

    protected $description = 'Lint dynamic schema/layout/template contracts before publish.';

    public function handle(DynamicSchemaLinterInterface $linter): int
    {
        $strict = (bool) $this->option('strict');
        $changedOnly = (bool) $this->option('changed-only');
        $asJson = (bool) $this->option('json');

        foreach ([
            'entity_definitions',
            'field_definitions',
            'layout_definitions',
            'dynamic_print_templates',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return $this->emitFailure(
                    "Missing required table: {$table}",
                    $asJson
                );
            }
        }

        $report = $linter->lint($strict, $changedOnly);
        $errors = is_array($report['errors'] ?? null) ? $report['errors'] : [];
        $warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
        $meta = is_array($report['meta'] ?? null) ? $report['meta'] : [];

        if ($asJson) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(sprintf(
                'Dynamic Forms Lint (%s%s)',
                $strict ? 'strict' : 'non-strict',
                $changedOnly ? ', changed-only' : ''
            ));
            $this->line(sprintf('- Entities scanned: %d', (int) ($meta['entities_scanned'] ?? 0)));
            $this->line(sprintf('- Templates scanned: %d', (int) ($meta['templates_scanned'] ?? 0)));
            $this->line(sprintf('- Errors: %d', count($errors)));
            $this->line(sprintf('- Warnings: %d', count($warnings)));

            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }

                $category = (string) ($error['category'] ?? 'unknown');
                $reference = (string) ($error['reference'] ?? 'unknown');
                $message = (string) ($error['message'] ?? 'Unknown lint error.');
                $this->error("[{$category}] {$reference}: {$message}");
            }

            foreach ($warnings as $warning) {
                if (! is_array($warning)) {
                    continue;
                }

                $category = (string) ($warning['category'] ?? 'unknown');
                $reference = (string) ($warning['reference'] ?? 'unknown');
                $message = (string) ($warning['message'] ?? 'Unknown lint warning.');
                $this->warn("[{$category}] {$reference}: {$message}");
            }
        }

        if ($strict && $errors !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function emitFailure(string $message, bool $asJson): int
    {
        if ($asJson) {
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
