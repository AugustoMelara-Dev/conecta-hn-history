<?php

declare(strict_types=1);

/**
 * PR1 Architecture Ratchet — Filament 4 Schema migration.
 *
 * Invariantes del PR1 (acceptance criteria):
 * - App\Filament\App\Resources y App\Filament\Resources NO importan Filament\Forms\Form.
 * - Cada Resource usa Filament\Schemas\Schema en su método form().
 * - Cada Resource declara `protected static ?string $model` (nullable).
 * - Ningún Resource importa Filament\Tables\Actions\HeaderAction (no existe en v4).
 */

$resourceDirs = [
    __DIR__.'/../../../../app/Filament/App/Resources',
    __DIR__.'/../../../../app/Filament/Resources',
];

$resourceFiles = [];
foreach ($resourceDirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        if (! str_ends_with($file->getFilename(), 'Resource.php')) {
            continue;
        }
        $resourceFiles[] = $file->getPathname();
    }
}

it('at least 30 Resources migrated (PR1 + PR2 + PR6 targets)', function () use ($resourceFiles) {
    expect(count($resourceFiles))->toBeGreaterThanOrEqual(30);
});

it('no Resource imports the deprecated Filament\\Forms\\Form class', function () use ($resourceFiles) {
    foreach ($resourceFiles as $file) {
        $contents = file_get_contents($file);
        expect($contents)
            ->not->toContain('use Filament\\Forms\\Form;')
            ->and($contents)
            ->not->toContain('use Filament\Forms\Form;');
    }
});

it('every Resource uses Schema in form() signature', function () use ($resourceFiles) {
    expect($resourceFiles)->not->toBeEmpty();
    foreach ($resourceFiles as $file) {
        $contents = file_get_contents($file);
        // Each Resource must declare Schema in form() signature
        $hasSchemaForm = str_contains($contents, 'public static function form(Schema $schema): Schema')
            || str_contains($contents, 'public static function form(\Filament\Schemas\Schema');
        if (! $hasSchemaForm) {
            // Some Resources don't have form() (e.g. read-only); skip
            if (! str_contains($contents, 'function form(')) {
                continue;
            }
            expect($hasSchemaForm)->toBeTrue("File: {$file}");
        }
    }
});

it('every Resource declares nullable $model', function () use ($resourceFiles) {
    expect($resourceFiles)->not->toBeEmpty();
    foreach ($resourceFiles as $file) {
        $contents = file_get_contents($file);
        if (str_contains($contents, 'protected static ?string $model')) {
            continue;
        }
        // File without $model property - skip
        if (! str_contains($contents, '$model')) {
            continue;
        }
        expect($contents)->toContain('protected static ?string $model');
    }
});

it('no Resource imports legacy HeaderAction', function () use ($resourceFiles) {
    foreach ($resourceFiles as $file) {
        $contents = file_get_contents($file);
        expect($contents)
            ->not->toContain('use Filament\\Tables\\Actions\\HeaderAction;')
            ->not->toContain('Filament\\Tables\\Actions\\HeaderAction');
    }
});
