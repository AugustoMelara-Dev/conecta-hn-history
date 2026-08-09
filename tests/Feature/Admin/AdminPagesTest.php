<?php

declare(strict_types=1);
use App\Filament\Admin\Pages\AiRegistry;
use App\Filament\Admin\Pages\ArchitectureHealth;
use App\Filament\Admin\Pages\CrossTenantReports;
use App\Filament\Admin\Pages\GlobalSettings;
use App\Filament\Admin\Pages\IncidentLog;
use App\Filament\Admin\Pages\JobsQueue;
use App\Filament\Admin\Pages\SystemMetrics;
use App\Filament\Admin\Pages\TenantsOverview;

it('Admin ArchitectureHealth page exists', function () {
    expect(class_exists(ArchitectureHealth::class))->toBeTrue();
});

it('Admin SystemMetrics page exists', function () {
    expect(class_exists(SystemMetrics::class))->toBeTrue();
});

it('Admin AiRegistry page exists', function () {
    expect(class_exists(AiRegistry::class))->toBeTrue();
});

it('Admin CrossTenantReports page exists', function () {
    expect(class_exists(CrossTenantReports::class))->toBeTrue();
});

it('Admin IncidentLog page exists', function () {
    expect(class_exists(IncidentLog::class))->toBeTrue();
});

it('Admin GlobalSettings page exists', function () {
    expect(class_exists(GlobalSettings::class))->toBeTrue();
});

it('Admin TenantsOverview page exists', function () {
    expect(class_exists(TenantsOverview::class))->toBeTrue();
});

it('Admin JobsQueue page exists', function () {
    expect(class_exists(JobsQueue::class))->toBeTrue();
});

it('8 admin pages total exist', function () {
    $pages = [
        ArchitectureHealth::class,
        SystemMetrics::class,
        AiRegistry::class,
        CrossTenantReports::class,
        IncidentLog::class,
        GlobalSettings::class,
        TenantsOverview::class,
        JobsQueue::class,
    ];
    expect(count($pages))->toBe(8);
    foreach ($pages as $p) {
        expect(class_exists($p))->toBeTrue();
    }
});
