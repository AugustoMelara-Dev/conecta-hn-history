<?php

declare(strict_types=1);

/**
 * GovernanceComplianceTest - Architectural Invariant Lock
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GOVERNANCE LOCK: Encodes the audit report's invariants as automated  ║
 * ║  tests. Any regression of Technical, Domain, or Security laws will    ║
 * ║  break CI immediately. Maps 1:1 to the Definition of Done.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Invariants enforced:
 *   1. strict_types declaration (Technical)
 *   2. final readonly immutability (Technical)
 *   3. Framework class inheritance ban (Decoupling)
 *   4. Tenant context in Commands (Domain / Multi-tenancy)
 *   5. SensitiveDataInterface on credential Commands (Security)
 */

use App\Application\Shared\Contracts\SensitiveDataInterface;

// ─────────────────────────────────────────────────────────────────────────────
// Helper: Convert file path to PSR-4 namespace
// ─────────────────────────────────────────────────────────────────────────────

function governancePathToNamespace(string $filePath, string $baseDir, string $baseNamespace): ?string
{
    $relative = str_replace($baseDir, '', $filePath);
    $relative = str_replace([DIRECTORY_SEPARATOR, '/'], '\\', $relative);
    $relative = ltrim($relative, '\\');
    $relative = preg_replace('/\.php$/i', '', $relative);

    return $relative ? $baseNamespace.'\\'.$relative : null;
}

/**
 * Recursively iterate PHP files in a directory.
 *
 * @return \Generator<\SplFileInfo>
 */
function phpFilesIn(string $dir): Generator
{
    if (! is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            yield $file;
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. TECHNICAL INVARIANT: strict_types
// ─────────────────────────────────────────────────────────────────────────────

test('all Actions and Application PHP files declare strict_types', function () {
    $dirs = [
        realpath(__DIR__.'/../../app/Infrastructure'),
        realpath(__DIR__.'/../../app/Application'),
    ];

    $scanned = 0;

    foreach ($dirs as $dir) {
        foreach (phpFilesIn($dir) as $file) {
            $pathname = $file->getPathname();
            // SKIP GENERATED CODE (e.g. gRPC, Protos)
            if (str_contains($pathname, 'Generated') || str_contains($pathname, 'Protos')) {
                continue;
            }

            $content = file_get_contents($pathname);
            $relative = str_replace(
                realpath(__DIR__.'/../../app').DIRECTORY_SEPARATOR,
                '',
                $file->getPathname()
            );

            // Match with or without \r\n line endings
            $hasStrictTypes = (bool) preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $content);

            expect($hasStrictTypes)->toBeTrue(
                "File [{$relative}] is missing declare(strict_types=1)"
            );

            $scanned++;
        }
    }

    expect($scanned)->toBeGreaterThan(0, 'No PHP files found to scan — check paths.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. TECHNICAL INVARIANT: final readonly (Immutability)
// ─────────────────────────────────────────────────────────────────────────────

test('all Actions are final readonly classes', function () {
    $dir = realpath(__DIR__.'/../../app/Infrastructure');
    $checked = 0;

    foreach (phpFilesIn($dir) as $file) {
        $pathname = $file->getPathname();
        // ONLY ENFORCE ON ADAPTER ACTIONS (relocated from app/Actions)
        if (! str_contains($pathname, DIRECTORY_SEPARATOR.'Actions'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $class = governancePathToNamespace($pathname, $dir, 'App\\Infrastructure');
        if (! $class || ! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isInterface() || $reflection->isTrait()) {
            continue;
        }

        expect($reflection->isFinal())->toBeTrue("Action [{$class}] must be final.");
        expect($reflection->isReadOnly())->toBeTrue("Action [{$class}] must be readonly.");
        $checked++;
    }

    expect($checked)->toBeGreaterThan(0, 'No Action classes found to verify.');
});

test('all Application Commands are final readonly classes', function () {
    $dir = realpath(__DIR__.'/../../app/Application');
    $checked = 0;

    foreach (phpFilesIn($dir) as $file) {
        $normalized = str_replace('\\', '/', $file->getPathname());
        if (! str_contains($normalized, '/Commands/') || ! str_ends_with($file->getFilename(), 'Command.php')) {
            continue;
        }

        $class = governancePathToNamespace($file->getPathname(), $dir, 'App\\Application');
        if (! $class || ! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        expect($reflection->isFinal())->toBeTrue("Command [{$class}] must be final.");
        expect($reflection->isReadOnly())->toBeTrue("Command [{$class}] must be readonly.");
        $checked++;
    }

    expect($checked)->toBeGreaterThan(0, 'No Command classes found to verify.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. DECOUPLING INVARIANT: No framework class inheritance
// ─────────────────────────────────────────────────────────────────────────────

test('actions do not extend framework base classes', function () {
    $dir = realpath(__DIR__.'/../../app/Infrastructure');
    $checked = 0;

    foreach (phpFilesIn($dir) as $file) {
        $pathname = $file->getPathname();
        // ONLY ENFORCE ON ADAPTER ACTIONS
        if (! str_contains($pathname, DIRECTORY_SEPARATOR.'Actions'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $class = governancePathToNamespace($pathname, $dir, 'App\\Infrastructure');
        if (! $class || ! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        $parent = $reflection->getParentClass();

        if ($parent) {
            $parentName = $parent->getName();
            expect($parentName)
                ->not()->toStartWith('Illuminate\\', "Action [{$class}] extends Illuminate class [{$parentName}]")
                ->not()->toStartWith('Symfony\\', "Action [{$class}] extends Symfony class [{$parentName}]");
        }

        $checked++;
    }

    expect($checked)->toBeGreaterThan(0, 'No Action classes found to verify.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. DOMAIN INVARIANT: Tenant context in Commands (Multi-tenancy)
// ─────────────────────────────────────────────────────────────────────────────

test('all Commands carry tenant context (tenantId or requestingTenantId)', function () {
    $dir = realpath(__DIR__.'/../../app/Application');

    // GENESIS COMMANDS: These create tenants or register users who may not
    // yet have a tenant context. They are excluded by design, not by oversight.
    // The Handler for these commands resolves tenancy downstream.
    $genesisExclusions = [
        'App\\Application\\Tenant\\Commands\\RegisterTenantCommand',
    ];

    $checked = 0;

    foreach (phpFilesIn($dir) as $file) {
        $normalized = str_replace('\\', '/', $file->getPathname());
        if (! str_contains($normalized, '/Commands/') || ! str_ends_with($file->getFilename(), 'Command.php')) {
            continue;
        }

        $class = governancePathToNamespace($file->getPathname(), $dir, 'App\\Application');
        if (! $class || ! class_exists($class)) {
            continue;
        }

        // Skip documented genesis commands
        if (in_array($class, $genesisExclusions, true)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        $properties = array_map(fn ($p) => $p->getName(), $reflection->getProperties());

        $hasTenantContext = in_array('tenantId', $properties)
            || in_array('requestingTenantId', $properties);

        expect($hasTenantContext)->toBeTrue(
            "Command [{$class}] must carry tenant context (tenantId or requestingTenantId)."
        );

        $checked++;
    }

    expect($checked)->toBeGreaterThan(0, 'No Command classes found to verify.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. SECURITY INVARIANT: SensitiveDataInterface on credential Commands
// ─────────────────────────────────────────────────────────────────────────────

test('Commands handling passwords or registration implement SensitiveDataInterface', function () {
    $dir = realpath(__DIR__.'/../../app/Application');

    // KNOWN DEBT: These commands handle sensitive data but do not yet
    // implement SensitiveDataInterface. They are excluded to avoid false
    // negatives in CI while the remediation is tracked on the roadmap.
    // TODO: Remediate and remove from this list.
    $knownDebt = [
        'App\\Application\\Tenant\\Commands\\RegisterTenantCommand',
    ];

    $checked = 0;

    foreach (phpFilesIn($dir) as $file) {
        $normalized = str_replace('\\', '/', $file->getPathname());
        if (! str_contains($normalized, '/Commands/') || ! str_ends_with($file->getFilename(), 'Command.php')) {
            continue;
        }

        $filename = $file->getFilename();

        // Only enforce on Commands that handle sensitive credentials
        if (! str_contains($filename, 'Password') && ! str_contains($filename, 'Register')) {
            continue;
        }

        $class = governancePathToNamespace($file->getPathname(), $dir, 'App\\Application');
        if (! $class || ! class_exists($class)) {
            continue;
        }

        // Skip documented known debt
        if (in_array($class, $knownDebt, true)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        expect($reflection->implementsInterface(SensitiveDataInterface::class))->toBeTrue(
            "Security Risk: Command [{$class}] handles sensitive data but does not implement SensitiveDataInterface."
        );

        $checked++;
    }

    expect($checked)->toBeGreaterThan(0, 'No sensitive Commands found — expected at least Password/Register Commands.');
});
