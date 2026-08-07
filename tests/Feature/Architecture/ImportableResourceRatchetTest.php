<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * PR4 Architecture Ratchet — Importable Resource coverage.
 *
 * Cada Import class debe estar en el registry de ImportService.
 */

it('ImportService exposes 9 entities', function () {
    $service = new \App\Application\Imports\ImportService();
    expect(count($service->entities()))->toBe(9);
});

it('Each Importable Resource has a corresponding Import class', function () {
    $service = new \App\Application\Imports\ImportService();

    foreach ($service->entities() as $entity) {
        expect($service->has($entity))->toBeTrue("Missing importer for {$entity}");

        // Verify the class implements Importable
        $class = $service->getImporterClass($entity);
        expect(class_exists($class))->toBeTrue();
        expect(is_subclass_of($class, \App\Application\Imports\Concerns\Importable::class))
            ->toBeTrue("Class {$class} must implement Importable");
    }
});

it('All Import files have valid PHP syntax', function () {
    $importsDir = app_path('Application/Imports/Imports');
    $files = File::allFiles($importsDir);
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        expect(File::get($file->getPathname()))->toContain('declare(strict_types=1);');
    }
});
