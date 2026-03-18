<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use Illuminate\Console\Command;
use ReflectionEnum;

/**
 * GenerateTypeScriptEnums
 *
 * Bridges PHP Domain Enums to TypeScript for strict frontend typing.
 * Implements "The Mirror Rule" (Reflection) and "The Real Enum Rule" (TS Enums).
 */
class GenerateTypeScriptEnums extends Command
{
    protected $signature = 'schema:generate-enums';

    protected $description = 'Generate TypeScript Enums from Domain PHP Enums';

    private const CLASSES_TO_SCAN = [
        UserRole::class,
        TenantPlan::class,
    ];

    public function handle(): int
    {
        $outputPath = base_path('resources/js/types/Enums.ts');

        $buffer = "// ⚠️ AUTO-GENERATED FILE. DO NOT EDIT MANUALLY.\n\n";

        foreach (self::CLASSES_TO_SCAN as $class) {
            if (! enum_exists($class)) {
                $this->error("Enum [{$class}] not found.");

                continue;
            }

            $reflection = new ReflectionEnum($class);
            $shortName = $reflection->getShortName();

            // Open TS Enum Definition
            $buffer .= "export enum {$shortName} {\n";

            foreach ($reflection->getCases() as $case) {
                $name = $case->name;
                $value = $case->getBackingValue();

                // Indented Case
                $buffer .= "    {$name} = '{$value}',\n";
            }

            // Close Definition
            $buffer .= "}\n\n";
        }

        $this->ensureDirectoryExists($outputPath);
        file_put_contents($outputPath, $buffer);

        $count = count(self::CLASSES_TO_SCAN);
        $this->info("Successfully bridged {$count} Enums to [{$outputPath}]");

        return Command::SUCCESS;
    }

    private function ensureDirectoryExists(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
