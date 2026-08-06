<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * PR1 — Filament 4 base + admin skeleton
 * Acceptance: php artisan filament pasa, ambos paneles renderizan, composer.json declara filament/filament: ^4.0.
 */

it('composer.json declares filament/filament at ^4.0', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect($composer['require'])->toHaveKey('filament/filament')
        ->and($composer['require']['filament/filament'])->toMatch('/\^4\./');
});

it('AppPanelProvider uses Filament 4 navigation groups', function () {
    $contents = file_get_contents(app_path('Providers/Filament/AppPanelProvider.php'));

    expect($contents)->toContain('->navigationGroups(')
        ->and($contents)->toContain('->databaseNotifications()')
        ->and($contents)->toContain('->viteTheme(')
        ->and($contents)->toContain('->tenantMiddleware(')
        ->and($contents)->toContain('->tenantRoutePrefix(')
        ->and($contents)->toContain('->breadcrumbs()');
});

it('AdminPanelProvider uses Filament 4 navigation groups and database notifications', function () {
    $contents = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($contents)->toContain('->navigationGroups(')
        ->and($contents)->toContain('->databaseNotifications()')
        ->and($contents)->toContain('->viteTheme(')
        ->and($contents)->toContain('->breadcrumbs()');
});

it('filament:assets command can be invoked', function () {
    Artisan::call('filament:assets');

    expect(Artisan::output())->not->toThrow();
});
