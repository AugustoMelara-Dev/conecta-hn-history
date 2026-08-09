<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * PR2 Architecture Ratchet — CRM Domain isolation.
 *
 * Garantiza que el código en app/Domain/Crm/ no importa Filament ni App\Models.
 * (Domain layer debe ser puro PHP, sin dependencias de UI.)
 */
it('CRM Domain files do not import Filament', function () {
    $domainDir = app_path('Domain/Crm');
    expect(is_dir($domainDir))->toBeTrue();

    $files = File::allFiles($domainDir);
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = File::get($file->getPathname());
        expect($contents)
            ->not->toContain('Filament\\')
            ->not->toContain('Filament\\Forms\\')
            ->not->toContain('Filament\\Schemas\\')
            ->not->toContain('Filament\\Tables\\');
    }
});

it('CRM Domain files do not import Eloquent Models', function () {
    $domainDir = app_path('Domain/Crm');
    $files = File::allFiles($domainDir);

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = File::get($file->getPathname());
        // Domain can reference value objects, enums, contracts, but NOT Eloquent models
        expect($contents)->not->toMatch('/use App\\\\Models\\\\(?!Contracts)/');
    }
});

it('CRM Domain uses strict types', function () {
    $domainDir = app_path('Domain/Crm');
    $files = File::allFiles($domainDir);

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = File::get($file->getPathname());
        expect($contents)->toContain('declare(strict_types=1);');
    }
});
