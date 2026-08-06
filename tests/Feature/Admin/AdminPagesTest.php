<?php

declare(strict_types=1);

it('Admin ArchitectureHealth page exists', function () {
    expect(class_exists(\App\Filament\Admin\Pages\ArchitectureHealth::class))->toBeTrue();
});

it('Admin SystemMetrics page exists', function () {
    expect(class_exists(\App\Filament\Admin\Pages\SystemMetrics::class))->toBeTrue();
});

it('Admin AiRegistry page exists', function () {
    expect(class_exists(\App\Filament\Admin\Pages\AiRegistry::class))->toBeTrue();
});

it('Admin CrossTenantReports page exists', function () {
    expect(class_exists(\App\Filament\Admin\Pages\CrossTenantReports::class))->toBeTrue();
});

it('Admin IncidentLog page exists', function () {
    expect(class_exists(\App\Filament\Admin\Pages\IncidentLog::class))->toBeTrue();
});

it('Admin GlobalSettings page exists', function () {
    expect(class_exists(\App\Filament\Admin\Pages\GlobalSettings::class))->toBeTrue();
});

it('Admin TenantsOverview page exists', function () {
    expect(class_exists(\App\Filament\Admin\Pages\TenantsOverview::class))->toBeTrue();
});

it('Admin JobsQueue page exists', function () {
    expect(class_exists(\App\Filament\Admin\Pages\JobsQueue::class))->toBeTrue();
});

it('8 admin pages total exist', function () {
    $pages = [
        \App\Filament\Admin\Pages\ArchitectureHealth::class,
        \App\Filament\Admin\Pages\SystemMetrics::class,
        \App\Filament\Admin\Pages\AiRegistry::class,
        \App\Filament\Admin\Pages\CrossTenantReports::class,
        \App\Filament\Admin\Pages\IncidentLog::class,
        \App\Filament\Admin\Pages\GlobalSettings::class,
        \App\Filament\Admin\Pages\TenantsOverview::class,
        \App\Filament\Admin\Pages\JobsQueue::class,
    ];
    expect(count($pages))->toBe(8);
    foreach ($pages as $p) {
        expect(class_exists($p))->toBeTrue();
    }
});
