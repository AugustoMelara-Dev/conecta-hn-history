<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateTypeScriptInterfacesCommand extends Command
{
    protected $signature = 'schema:generate-types';

    protected $description = 'Generate TypeScript definitions from JSON Schemas (Titanium Bridge)';

    public function handle(): int
    {
        $schemaPath = resource_path('schemas');
        $outputPath = resource_path('js/Types/Generated');

        if (! File::exists($schemaPath)) {
            $this->error("Schema directory not found at [{$schemaPath}]");

            return Command::FAILURE;
        }

        if (! File::exists($outputPath)) {
            File::makeDirectory($outputPath, 0755, true);
        }

        $files = File::files($schemaPath);
        $count = 0;

        $this->info('Bridging Backend Schemas to Frontend Types...');

        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            try {
                $this->processSchema($file, $outputPath);
                $count++;
            } catch (\Throwable $e) {
                $this->error("Failed to process {$file->getFilename()}: ".$e->getMessage());
            }
        }

        $this->info("Successfully generated {$count} TypeScript interfaces.");

        return Command::SUCCESS;
    }

    private function processSchema(\SplFileInfo $file, string $outputPath): void
    {
        $content = json_decode(file_get_contents($file->getPathname()), true);

        // Strategy: Interface Name from Title or Filename
        $interfaceName = $content['title'] ?? Str::studly($file->getBasename('.json'));
        $requiredFields = $content['required'] ?? [];
        $properties = $content['properties'] ?? [];

        $tsProps = [];

        foreach ($properties as $field => $config) {
            $isOptional = ! in_array($field, $requiredFields);
            $tsType = $this->mapJsonTypeToTsType($config, $field);

            $tsProps[] = "    {$field}".($isOptional ? '?' : '').": {$tsType};";
        }

        $tsContent = $this->buildTsFile($interfaceName, $tsProps);

        file_put_contents(
            $outputPath.DIRECTORY_SEPARATOR."{$interfaceName}.d.ts",
            $tsContent
        );

        $this->line("  - Generated: {$interfaceName}.d.ts");
    }

    private function mapJsonTypeToTsType(mixed $typeDefinition, string $fieldName): string
    {
        $type = $typeDefinition['type'] ?? 'any';

        // 1. Handle Nullable Unions: ["string", "null"]
        if (is_array($type)) {
            if (in_array('null', $type)) {
                $nonNullTypes = array_values(array_filter($type, fn ($t) => $t !== 'null'));

                if (count($nonNullTypes) === 1) {
                    // Simple nullable: string | null
                    $baseType = $this->resolvePrimitive($nonNullTypes[0], $typeDefinition, $fieldName);

                    return "{$baseType} | null";
                }

                // Complex union
                $mapped = array_map(fn ($t) => $this->resolvePrimitive($t, $typeDefinition, $fieldName), $nonNullTypes);

                return implode(' | ', $mapped).' | null';
            }

            // Non-nullable union
            $mapped = array_map(fn ($t) => $this->resolvePrimitive($t, $typeDefinition, $fieldName), $type);

            return implode(' | ', $mapped);
        }

        return $this->resolvePrimitive($type, $typeDefinition, $fieldName);
    }

    private function resolvePrimitive(string $type, array $config, string $fieldName): string
    {
        return match ($type) {
            'string' => 'string',
            'integer', 'number' => 'number',
            'boolean' => 'boolean',
            'array' => $this->resolveArray($config, $fieldName),
            'object' => 'Record<string, any>',
            default => $this->fallbackType($type, $fieldName),
        };
    }

    private function resolveArray(array $config, string $fieldName): string
    {
        $items = $config['items'] ?? null;
        if (! $items) {
            return 'any[]';
        }

        // Recursive resolution
        $innerType = $this->mapJsonTypeToTsType($items, "{$fieldName}.items");

        // Handle parentheses for union types in arrays
        if (str_contains($innerType, '|') || str_contains($innerType, ' ')) {
            return "({$innerType})[]";
        }

        return "{$innerType}[]";
    }

    private function fallbackType(string $type, string $fieldName): string
    {
        $this->warn("Warning: Unknown type [{$type}] for field [{$fieldName}], defaulting to 'any'.");

        return 'any';
    }

    private function buildTsFile(string $interfaceName, array $props): string
    {
        $propsString = implode("\n", $props);

        return <<<TS
/* AUTO-GENERATED FILE. DO NOT EDIT. */

export interface {$interfaceName} {
{$propsString}
}

TS;
    }
}
