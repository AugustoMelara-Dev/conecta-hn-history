<?php

declare(strict_types=1);

/**
 * RiskComplianceTest - Critical Risk Evaluation Governance
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  Maps directly to the "Critical Risk Evaluation" audit findings.       ║
 * ║  HIGH: Serialization — No Eloquent Models in Application Commands.     ║
 * ║  MEDIUM: Scalability — Metadata payload capped at 10KB.               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

test('risk mitigation - commands do not carry heavy eloquent models', function () {
    // Scans ALL Application Commands to prevent "The Eloquent Parasite"
    // Mitigates: HIGH Severity Serialization / Tenant Leakage Risk
    $commandsDir = __DIR__ . '/../../app/Application';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($commandsDir, FilesystemIterator::SKIP_DOTS)
    );

    $scanned = 0;
    foreach ($iterator as $file) {
        if (!str_ends_with($file->getFilename(), 'Command.php')) {
            continue;
        }

        // Derive FQCN from path: app/Application/Auth/Commands/X.php → App\Application\Auth\Commands\X
        $relativePath = str_replace(
            [$commandsDir . DIRECTORY_SEPARATOR, '/', '.php'],
            ['', '\\', ''],
            $file->getPathname()
        );
        $class = 'App\\Application\\' . $relativePath;

        if (!class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        foreach ($reflection->getProperties() as $property) {
            $type = $property->getType();
            if ($type && !$type->isBuiltin()) {
                expect($type->getName())
                    ->not->toBe('App\Models\User', "Command {$class} carries Eloquent User model")
                    ->not->toContain('Illuminate\Database\Eloquent', "Command {$class} carries Eloquent dependency");
            }
        }
        $scanned++;
    }

    expect($scanned)->toBeGreaterThan(0, 'No Command files were scanned — check directory structure');
});

test('risk mitigation - interaction action validates metadata size', function () {
    // Mitigates: MEDIUM Severity Scalability Risk (Queue/DB DoS via large payloads)
    $content = file_get_contents(__DIR__ . '/../../app/Actions/Interactions/RecordInteractionAction.php');
    expect($content)
        ->toContain("'metadata'")
        ->toContain('10240')
        ->toContain('json_encode');
});
