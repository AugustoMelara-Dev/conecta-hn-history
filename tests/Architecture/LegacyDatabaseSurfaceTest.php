<?php

declare(strict_types=1);

use Illuminate\Support\Str;

it('keeps legacy database table references limited to documented compatibility surfaces', function (): void {
    $basePath = dirname(__DIR__, 2);

    $legacyReferences = [
        'domain_outbox_messages' => [
            'app/Infrastructure/Persistence/Eloquent/Models/OutboxMessage.php',
            'tests/Feature/Database/PlatformLedgerSchemaTest.php',
        ],
        'domain_outbox' => [
            'tests/Feature/Database/PlatformLedgerSchemaTest.php',
            'tests/Feature/Infrastructure/Scale10xTest.php',
        ],
        'idempotency_keys' => [
            'app/Infrastructure/Common/Models/IdempotencyKey.php',
            'tests/Feature/Database/PlatformLedgerSchemaTest.php',
        ],
    ];

    $roots = [
        $basePath.DIRECTORY_SEPARATOR.'app',
        $basePath.DIRECTORY_SEPARATOR.'tests',
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
            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            $relativePath = Str::of($path)
                ->replace($basePath.DIRECTORY_SEPARATOR, '')
                ->replace(DIRECTORY_SEPARATOR, '/')
                ->value();

            if ($relativePath === 'tests/Architecture/LegacyDatabaseSurfaceTest.php') {
                continue;
            }

            foreach ($legacyReferences as $table => $allowedPaths) {
                $hasTableReference = preg_match(
                    '/(?<![A-Za-z0-9_])'.preg_quote($table, '/').'(?![A-Za-z0-9_])/',
                    $contents
                ) === 1;

                if (! $hasTableReference) {
                    continue;
                }

                if (! in_array($relativePath, $allowedPaths, true)) {
                    $violations[] = "{$relativePath} references {$table}";
                }
            }
        }
    }

    expect($violations)->toBeEmpty();
});
