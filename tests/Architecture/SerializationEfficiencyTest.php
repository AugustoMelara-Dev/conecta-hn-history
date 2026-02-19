<?php

declare(strict_types=1);

/**
 * SerializationEfficiencyTest - Command Payload Weight Guard
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SCALABILITY INVARIANT: Commands must contain only serializable       ║
 * ║  primitives (string, int, float, bool, array, null). No Eloquent     ║
 * ║  Models, Closures, or resources — they break Queue/Outbox consumers. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

test('commands contain only serializable primitive types', function () {
    $dir = realpath(__DIR__ . '/../../app/Application');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $checked = 0;

    // Allowed built-in types for Command properties
    $allowedBuiltins = ['string', 'int', 'float', 'bool', 'array', 'null', 'mixed'];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $normalized = str_replace('\\', '/', $file->getPathname());
        if (! str_contains($normalized, '/Commands/') || ! str_ends_with($file->getFilename(), 'Command.php')) {
            continue;
        }

        // Resolve FQCN from file path
        $relative = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $className = 'App\\Application\\' . str_replace(
            [DIRECTORY_SEPARATOR, '/', '.php'],
            ['\\', '\\', ''],
            $relative
        );

        if (! class_exists($className)) {
            continue;
        }

        $reflection = new ReflectionClass($className);
        $checked++;

        foreach ($reflection->getProperties() as $property) {
            $type = $property->getType();

            // No type hint = dynamic, acceptable for now
            if ($type === null) {
                continue;
            }

            // Handle union types (e.g., string|null)
            if ($type instanceof ReflectionUnionType) {
                foreach ($type->getTypes() as $unionType) {
                    if ($unionType instanceof ReflectionNamedType) {
                        $typeName = $unionType->getName();
                        $isAllowed = $unionType->isBuiltin() || in_array($typeName, $allowedBuiltins, true);
                        expect($isAllowed)->toBeTrue(
                            "Command [{$className}] property [\${$property->getName()}] has non-primitive union member [{$typeName}]."
                        );
                    }
                }
                continue;
            }

            // Handle named types
            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();

                // Built-in primitives are always allowed
                if ($type->isBuiltin() || in_array($typeName, $allowedBuiltins, true)) {
                    continue;
                }

                // Reject heavy framework objects
                $forbidden = [
                    'Illuminate\Database\Eloquent\Model',
                    'Closure',
                ];

                foreach ($forbidden as $forbiddenType) {
                    $isForbidden = is_a($typeName, $forbiddenType, true);
                    expect($isForbidden)->toBeFalse(
                        "Command [{$className}] property [\${$property->getName()}] is type [{$typeName}] which extends [{$forbiddenType}]. Commands must contain only primitives."
                    );
                }
            }
        }
    }

    expect($checked)->toBeGreaterThan(0, 'No Command classes found to verify.');
});
