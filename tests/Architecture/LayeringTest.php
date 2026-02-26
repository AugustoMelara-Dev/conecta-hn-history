<?php

declare(strict_types=1);

/**
 * LayeringTest - Hexagonal Architecture Layer Enforcement
 *
 * Enforces: Input validation is an Infrastructure concern (Actions).
 * The Application Layer (Handlers) must trust its Command inputs
 * and NEVER perform its own input validation via framework tools.
 */

test('domain handlers do not perform input validation', function () {
    // Input validation belongs in the Action (Adapter) layer.
    // Handlers should receive valid, immutable Commands.
    $handlerDirs = [
        __DIR__ . '/../../app/Application/Auth/Handlers',
        __DIR__ . '/../../app/Application/Identity/Handlers',
        __DIR__ . '/../../app/Application/Interactions/Handlers',
        __DIR__ . '/../../app/Application/User/Handlers',
    ];

    foreach ($handlerDirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }

        $files = glob($dir . '/*.php');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            expect($content)
                ->not->toContain('Validator::make', "Handler {$file} must not use Validator facade")
                ->not->toContain('use Illuminate\Support\Facades\Validator', "Handler {$file} must not import Validator facade")
                ->not->toContain('->validate()', "Handler {$file} must not call ->validate() — validation belongs in Actions");
        }
    }
});

test('actions readme contains refined architectural drivers', function () {
    $content = file_get_contents(__DIR__ . '/../../app/Actions/README.md');
    expect($content)
        ->toContain('Anti-Corruption Layer')
        ->toContain('Temporal Decoupling')
        ->toContain('Security Layer Separation');
});
