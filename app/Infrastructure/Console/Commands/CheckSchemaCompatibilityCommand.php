<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class CheckSchemaCompatibilityCommand extends Command
{
    protected $signature = 'schema:check-compatibility {--baseline= : Path to the baseline schemas directory}';
    protected $description = 'Verify backward compatibility against a baseline version';

    public function handle(): int
    {
        $currentPath = resource_path('schemas');
        $baselinePath = $this->option('baseline') ?? resource_path('schemas_baseline');

        if (!is_dir($baselinePath)) {
            $this->warn("Baseline directory not found at [{$baselinePath}]. Assuming initial release (First Blood).");
            return Command::SUCCESS;
        }

        $this->info("Comparing Current [{$currentPath}] vs Baseline [{$baselinePath}]...");

        $baselineFiles = glob($baselinePath . '/*.json');
        $violations = [];

        foreach ($baselineFiles as $baselineFile) {
            $filename = basename($baselineFile);
            $currentFile = $currentPath . DIRECTORY_SEPARATOR . $filename;

            if (!file_exists($currentFile)) {
                $violations[] = [
                    'File' => $filename,
                    'Issue' => 'Schema Removed',
                    'Severity' => 'CRITICAL'
                ];
                continue;
            }

            $baselineSchema = json_decode(file_get_contents($baselineFile), true);
            $currentSchema = json_decode(file_get_contents($currentFile), true);

            // DETECTOR DE VIOLACIONES DE LA LEY
            $fileViolations = $this->detectViolations($baselineSchema, $currentSchema);
            
            foreach ($fileViolations as $v) {
                $violations[] = array_merge(['File' => $filename], $v);
            }
        }

        if (count($violations) > 0) {
            $this->table(['File', 'Field', 'Violation', 'Severity'], $violations);
            $this->error('The Law of Non-Regression has been VIOLATED. Breaking changes detected.');
            return Command::FAILURE;
        }

        $this->info('All schemas are Backward Compatible. The Law is intact.');
        return Command::SUCCESS;
    }

    private function detectViolations(array $baseline, array $current): array
    {
        $violations = [];

        // 1. Check Deleted Fields (Properties)
        $baselineProps = $baseline['properties'] ?? [];
        $currentProps = $current['properties'] ?? [];

        foreach ($baselineProps as $field => $config) {
            if (!array_key_exists($field, $currentProps)) {
                $violations[] = [
                    'Field' => $field,
                    'Violation' => 'Field Removed (Destructive Change)',
                    'Severity' => 'FATAL'
                ];
                continue;
            }

            // 2. Check Type Mutation
            $baselineType = $this->normalizeType($config['type'] ?? 'any');
            $currentType = $this->normalizeType($currentProps[$field]['type'] ?? 'any');

            if ($baselineType !== $currentType) {
                // Warning: This is strict equality. 
                // In a real developer portal we might allow expanding types (e.g. string -> [string, null]),
                // but for Titanium Standard, we enforce strict type stability.
                $violations[] = [
                    'Field' => $field,
                    'Violation' => "Type Mutated: [{$baselineType}] -> [{$currentType}]",
                    'Severity' => 'FATAL'
                ];
            }
        }

        // 3. Check Required Fields Tightening (New Mandatory Fields)
        $baselineRequired = $baseline['required'] ?? [];
        $currentRequired = $current['required'] ?? [];

        // If a field is in CURRENT required but NOT in BASELINE required, it's a breaking change
        // (Clients sending old payloads will fail validation).
        foreach ($currentRequired as $reqField) {
            if (!in_array($reqField, $baselineRequired)) {
                // Exception: It is allowed IF the field is completely new.
                // But wait! If it's NEW and REQUIRED, old clients still break explicitly.
                // So: Adding a REQUIRED field is ALWAYS a breaking change.
                $violations[] = [
                    'Field' => $reqField,
                    'Violation' => 'Requirement Tightened (New Mandatory Field)',
                    'Severity' => 'FATAL'
                ];
            }
        }

        return $violations;
    }

    private function normalizeType(mixed $type): string
    {
        if (is_array($type)) {
            sort($type);
            return json_encode($type);
        }
        return (string) $type;
    }
}
