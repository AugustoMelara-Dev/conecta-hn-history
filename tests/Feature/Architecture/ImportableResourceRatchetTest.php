<?php

declare(strict_types=1);

use App\Application\Imports\Concerns\Importable;
use App\Application\Imports\ImportService;
use Illuminate\Support\Facades\File;

/**
 * PR4 Architecture Ratchet — Importable Resource coverage.
 *
 * Cada Import class debe estar en el registry de ImportService.
 */
it('ImportService exposes 9 entities', function () {
    $service = new ImportService;
    expect(count($service->entities()))->toBe(9);
});

it('Each Importable Resource has a corresponding Import class', function () {
    $service = new ImportService;

    foreach ($service->entities() as $entity) {
        expect($service->has($entity))->toBeTrue("Missing importer for {$entity}");

        // Verify the class implements Importable
        $class = $service->getImporterClass($entity);
        expect(class_exists($class))->toBeTrue();
        expect(is_subclass_of($class, Importable::class))
            ->toBeTrue("Class {$class} must implement Importable");
    }
});

it('All Import files have valid PHP syntax', function () {
    $importsDir = app_path('Application/Imports/Imports');
    $files = File::allFiles($importsDir);
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        expect(File::get($file->getPathname()))->toContain('declare(strict_types=1);');
    }
});
