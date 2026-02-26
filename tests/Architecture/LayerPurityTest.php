<?php

declare(strict_types=1);

/**
 * LayerPurityTest - Dependency Rule Enforcement
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL LAW: Application Layer DTOs must not depend on HTTP.    ║
 * ║  The Dependency Rule mandates Inner Circle ← Outer Circle direction.  ║
 * ║  Importing Illuminate\Http\Request in App\Application violates this.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

test('application layer dtos do not depend on http request', function () {
    $appDir = realpath(__DIR__ . '/../../app/Application');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relativePath = str_replace($appDir, '', $file->getPathname());

        expect($content)
            ->not->toContain(
                'use Illuminate\Http\Request;',
                "Application Layer file {$relativePath} must not import Illuminate\\Http\\Request"
            );
    }
});

test('application layer dtos do not contain fromRequest factory methods', function () {
    $appDir = realpath(__DIR__ . '/../../app/Application');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relativePath = str_replace($appDir, '', $file->getPathname());

        expect($content)
            ->not->toContain(
                'fromRequest(Request',
                "Application Layer file {$relativePath} must not have fromRequest(Request) factory"
            );
    }
});
