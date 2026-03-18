<?php

declare(strict_types=1);

/**
 * CommandSchemaTest - P3 Contract Testing Governance
 *
 * Enforces:
 * - SCHEMA-01: All Command properties are strictly typed (implicit schema)
 * - SCHEMA-02: JSON Schema snapshots prevent silent breaking changes in CI
 *   If an engineer alters a Command shape, the generated schema will drift
 *   from the snapshot, failing CI and requiring explicit acknowledgement.
 */

use App\Infrastructure\Schemas\JsonSchemaGenerator;

test('commands possess strict schema via typed properties', function () {
    // P3 Requirement: Prevent breaking changes by ensuring all Command properties are strictly typed.
    // This allows automatic JSON Schema generation in the future.
    $appDir = __DIR__.'/../../app/Application';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
    );

    $scanned = 0;

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        if (! str_contains($file->getFilename(), 'Command.php')) {
            continue;
        }

        $relativePath = str_replace(
            [realpath($appDir).DIRECTORY_SEPARATOR, '.php'],
            ['', ''],
            realpath($file->getPathname())
        );
        $className = 'App\\Application\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        if (! class_exists($className)) {
            continue;
        }

        $reflection = new ReflectionClass($className);
        $scanned++;

        foreach ($reflection->getProperties() as $property) {
            expect($property->hasType())
                ->toBeTrue("Schema Violation: Property [\${$property->getName()}] in [$className] must be strictly typed.");
        }
    }

    expect($scanned)->toBeGreaterThanOrEqual(10, 'Expected at least 10 Command classes to scan');
});

// ─────────────────────────────────────────────────────────────────────────────
// SCHEMA-02: Snapshot-Based Contract Verification
// ─────────────────────────────────────────────────────────────────────────────

test('commands adhere to committed JSON Schema contracts', function () {
    $generator = new JsonSchemaGenerator;
    $appDir = realpath(__DIR__.'/../../app/Application');
    $contractsDir = realpath(__DIR__.'/../Contracts') ?: __DIR__.'/../Contracts';

    if (! is_dir($contractsDir)) {
        mkdir($contractsDir, 0755, true);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
    );

    $verified = 0;

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $normalized = str_replace('\\', '/', $file->getPathname());
        if (! str_contains($normalized, '/Commands/') || ! str_ends_with($file->getFilename(), 'Command.php')) {
            continue;
        }

        $relativePath = str_replace(
            [realpath($appDir).DIRECTORY_SEPARATOR, '.php'],
            ['', ''],
            realpath($file->getPathname())
        );
        $className = 'App\\Application\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        if (! class_exists($className)) {
            continue;
        }

        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            continue;
        }

        // Skip classes without constructors (cannot generate schema)
        if (! $reflection->getConstructor()) {
            continue;
        }

        $schema = $generator->generate($className);
        $snapshotFile = $contractsDir.DIRECTORY_SEPARATOR.$reflection->getShortName().'.json';

        if (! file_exists($snapshotFile)) {
            // Baseline generation: create the snapshot for first-time runs
            file_put_contents($snapshotFile, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
            expect(true)->toBeTrue(); // Baseline created, mark pass
        } else {
            // Contract Verification: compare generated schema against snapshot
            $existingContract = json_decode(file_get_contents($snapshotFile), true);

            expect($schema)->toEqual(
                $existingContract,
                "Contract mismatch for [{$className}]. Breaking change detected! "
                ."If intentional, delete {$snapshotFile} to regenerate baseline, "
                .'and ensure an UpcasterInterface is created for old payloads.'
            );
        }

        $verified++;
    }

    expect($verified)->toBeGreaterThan(0, 'No Command classes found for contract verification.');
});
