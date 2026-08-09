<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

it('requires safety labels for every global scope bypass in application code', function (): void {
    $allowedLabels = [
        'ADMIN_CROSS_TENANT',
        'PUBLIC_READ_WITH_TENANT_FILTER',
        'JOB_GLOBAL_POLL',
        'WEBHOOK_LOCAL_INTENT_MATCH',
        'INFRA_TENANT_FILTER',
        'CANONICAL_WRITER_TENANT_FILTER',
        'MODEL_RELATION_TENANT_OWNED',
        'MIGRATION_OR_BACKFILL',
    ];

    $basePath = dirname(__DIR__, 2);

    $roots = [
        $basePath.DIRECTORY_SEPARATOR.'app',
        $basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders',
    ];

    $violations = [];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            $fileSafetyContext = implode("\n", array_slice($lines, 0, 12));
            $hasFileSafetyLabel = collect($allowedLabels)
                ->contains(fn (string $label): bool => str_contains(
                    $fileSafetyContext,
                    "@withoutGlobalScopes-safety {$label}",
                ));

            foreach ($lines as $index => $line) {
                if (! str_contains($line, 'withoutGlobalScopes')
                    && ! str_contains($line, 'withoutGlobalScope(')) {
                    continue;
                }

                $context = implode("\n", array_slice($lines, max(0, $index - 3), 4));
                $hasLabel = $hasFileSafetyLabel || collect($allowedLabels)
                    ->contains(fn (string $label): bool => str_contains($context, $label));

                if (! $hasLabel) {
                    $relativePath = Str::of($path)
                        ->replace($basePath.DIRECTORY_SEPARATOR, '')
                        ->replace(DIRECTORY_SEPARATOR, '/')
                        ->value();

                    $violations[] = sprintf('%s:%d', $relativePath, $index + 1);
                }
            }
        }
    }

    Assert::assertSame([], $violations, implode(PHP_EOL, $violations));
});
