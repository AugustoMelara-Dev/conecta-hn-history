<?php

declare(strict_types=1);

use Illuminate\Support\Str;

const BUSINESS_COMPONENTS_CSS = 'resources/css/filament/app/business-components.css';
const REPOSITORY_ROOT = __DIR__.'/../..';

it('loads the native business component stylesheet only in the app panel', function (): void {
    $provider = file_get_contents(REPOSITORY_ROOT.'/app/Providers/Filament/AppPanelProvider.php');
    $adminProvider = file_get_contents(REPOSITORY_ROOT.'/app/Providers/Filament/AdminPanelProvider.php');
    $vite = file_get_contents(REPOSITORY_ROOT.'/vite.config.js');

    expect($provider)
        ->toContain("Css::make('app-business-components')")
        ->toContain("Vite::asset('".BUSINESS_COMPONENTS_CSS."')")
        ->not->toContain('->viteTheme(')
        ->and($adminProvider)
        ->not->toContain('app-business-components')
        ->not->toContain(BUSINESS_COMPONENTS_CSS)
        ->and($vite)->toContain('"'.BUSINESS_COMPONENTS_CSS.'"');
});

it('keeps the panel extension native and independent from the tailwind toolchain', function (): void {
    $path = REPOSITORY_ROOT.'/'.BUSINESS_COMPONENTS_CSS;

    expect(is_file($path))->toBeTrue();

    $css = file_get_contents($path);

    expect($css)
        ->not->toContain('@tailwind')
        ->not->toContain('@apply')
        ->not->toContain('@import');
});

it('defines every business component token used by app panel blade views', function (): void {
    $path = REPOSITORY_ROOT.'/'.BUSINESS_COMPONENTS_CSS;

    expect(is_file($path))->toBeTrue();

    $css = file_get_contents($path);
    $tokens = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(REPOSITORY_ROOT.'/resources/views/filament/app', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! Str::endsWith($file->getFilename(), '.blade.php')) {
            continue;
        }

        preg_match_all('/\bbiz-[a-z0-9-]+\b/', file_get_contents($file->getPathname()), $matches);
        array_push($tokens, ...$matches[0]);
    }

    $externalAppViews = [
        REPOSITORY_ROOT.'/resources/views/filament/widgets/free-plan-upsell.blade.php',
    ];

    foreach ($externalAppViews as $view) {
        expect(is_file($view))->toBeTrue();
        preg_match_all('/\bbiz-[a-z0-9-]+\b/', file_get_contents($view), $matches);
        array_push($tokens, ...$matches[0]);
    }

    $tokens = array_values(array_unique($tokens));
    sort($tokens);

    expect($tokens)->not->toBeEmpty();

    foreach ($tokens as $token) {
        expect($css)->toMatch('/\\.'.preg_quote($token, '/').'(?=[\\s,{.:>])/');
    }
});
