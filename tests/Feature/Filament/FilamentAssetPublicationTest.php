<?php

declare(strict_types=1);

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

it('publishes the App business stylesheet from its canonical source', function (): void {
    $filesystem = app(Filesystem::class);
    $originalPublicPath = public_path();
    $temporaryPublicPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dovra-filament-assets-'.bin2hex(random_bytes(8));

    $filesystem->makeDirectory($temporaryPublicPath, 0755, true);

    try {
        app()->usePublicPath($temporaryPublicPath);

        $asset = collect(FilamentAsset::getStyles(['app']))
            ->first(fn (Css $css): bool => $css->getId() === 'app-business-components');

        expect($asset)->toBeInstanceOf(Css::class)
            ->and($asset->getPath())->toBe(resource_path('css/filament/app/business-components.css'));

        expect(Artisan::call('filament:assets'))->toBe(0);

        $publishedPath = $temporaryPublicPath.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'app-business-components.css';

        expect(is_file($publishedPath))->toBeTrue()
            ->and(file_get_contents($publishedPath))
            ->toBe(file_get_contents(resource_path('css/filament/app/business-components.css')));
    } finally {
        app()->usePublicPath($originalPublicPath);

        if (str_starts_with($temporaryPublicPath, sys_get_temp_dir().DIRECTORY_SEPARATOR.'dovra-filament-assets-')) {
            $filesystem->deleteDirectory($temporaryPublicPath);
        }
    }
});
