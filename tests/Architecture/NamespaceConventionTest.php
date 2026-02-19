<?php

declare(strict_types=1);

/**
 * NamespaceConventionTest - Namespace Governance
 *
 * Enforces:
 * - Application-layer ports live in App\Application\{Context}\Contracts
 * - App\Domain is a valid, active layer (NOT zombie scaffolding)
 * - No duplicate port definitions across layers
 */

test('contracts reside in application layer', function () {
    expect(interface_exists(\App\Application\Identity\Contracts\UserReadProjection::class))
        ->toBeTrue('UserReadProjection must exist in App\Application\Identity\Contracts');
});

test('domain layer contains active classes', function () {
    // App\Domain is NOT ghost scaffolding — it holds 99+ active files
    $domainDir = __DIR__ . '/../../app/Domain';
    expect(is_dir($domainDir))->toBeTrue('App\Domain must exist as the core domain layer');

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($domainDir, FilesystemIterator::SKIP_DOTS)
    );

    $phpFiles = 0;
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $phpFiles++;
        }
    }

    expect($phpFiles)->toBeGreaterThanOrEqual(50, 'App\Domain must contain active PHP classes');
});

test('no duplicate port definitions across layers', function () {
    // UserReadProjection must exist in Application, NOT duplicated in Domain
    expect(interface_exists(\App\Application\Identity\Contracts\UserReadProjection::class))
        ->toBeTrue();
    expect(interface_exists('App\Domain\Identity\Ports\UserReadProjection'))
        ->toBeFalse('Ports must not be duplicated in App\Domain\Identity\Ports');
});
