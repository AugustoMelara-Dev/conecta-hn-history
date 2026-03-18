<?php

declare(strict_types=1);

use App\Infrastructure\Schemas\JsonSchemaGenerator;

/**
 * ContractValidationTest - CI Gate for Command Schema Integrity
 *
 * Enforces: Every Command in the Application layer must produce
 * a valid JSON Schema. This prevents silent breaking changes
 * to the Command contract surface.
 *
 * @see App\Infrastructure\Schemas\JsonSchemaGenerator
 */
test('all commands generate valid json schemas', function () {
    $generator = new JsonSchemaGenerator;
    $applicationPath = realpath(__DIR__.'/../../app/Application');

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($applicationPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $commandCount = 0;

    foreach ($iterator as $file) {
        if (! str_contains($file->getFilename(), 'Command.php')) {
            continue;
        }

        // Derive FQCN from file path relative to Application/
        $relativePath = str_replace(
            [realpath($applicationPath).DIRECTORY_SEPARATOR, '/', '.php'],
            ['', '\\', ''],
            realpath($file->getPathname())
        );
        $className = 'App\\Application\\'.$relativePath;

        if (! class_exists($className)) {
            continue;
        }

        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            continue;
        }

        $schema = $generator->generate($className);

        expect($schema)
            ->toBeArray()
            ->toHaveKey('type', 'object')
            ->toHaveKey('properties')
            ->toHaveKey('required');

        // Validate properties are well-formed
        foreach ($schema['properties'] as $propName => $propDef) {
            expect($propDef)->toHaveKey('type');
        }

        $commandCount++;
    }

    // Safety: ensure we actually tested Commands (guards against path changes)
    expect($commandCount)->toBeGreaterThan(0, 'No Commands found — check app/Application path');
});

test('json schema generator produces valid json output', function () {
    $generator = new JsonSchemaGenerator;
    $schema = $generator->generate(\App\Application\Interactions\Commands\RecordInteractionCommand::class);

    $json = $generator->toJson($schema);

    expect($json)->toBeString();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['title'])->toBe('RecordInteractionCommand')
        ->and($decoded['type'])->toBe('object')
        ->and($decoded['additionalProperties'])->toBeFalse();
});

test('schema correctly maps nullable properties', function () {
    $generator = new JsonSchemaGenerator;
    $schema = $generator->generate(\App\Application\Interactions\Commands\RecordInteractionCommand::class);

    // productId is ?string — should be ["string", "null"]
    expect($schema['properties']['productId']['type'])
        ->toBeArray()
        ->toContain('string')
        ->toContain('null');

    // tenantId is string (required) — should be plain "string"
    expect($schema['properties']['tenantId']['type'])->toBe('string');
});

test('schema marks required vs optional properties correctly', function () {
    $generator = new JsonSchemaGenerator;
    $schema = $generator->generate(\App\Application\Auth\Commands\ResetUserPasswordCommand::class);

    // userId, password, tenantId are required (no default values)
    expect($schema['required'])
        ->toContain('userId')
        ->toContain('password')
        ->toContain('tenantId');

    // idempotencyKey has a default (null) — should NOT be in required
    expect($schema['required'])->not->toContain('idempotencyKey');
});
