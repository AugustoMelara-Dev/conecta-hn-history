<?php

declare(strict_types=1);

use App\Filament\App\Resources\AppointmentResource;
use App\Filament\App\Resources\ServiceProviderResource;
use App\Filament\App\Resources\ServiceResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;

// ═══════════════════════════════════════════════════════════════════════
// SERVICE DOMAIN FILAMENT RESOURCES TESTS
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: ServiceResource is properly configured
it('ServiceResource is properly configured', function () {
    expect(class_exists(ServiceResource::class))->toBeTrue();
    expect(ServiceResource::getModel())->toBe(Service::class);
    expect(ServiceResource::getNavigationGroup())->toBe('Servicios');
});

// TEST 2: ServiceProviderResource is properly configured
it('ServiceProviderResource is properly configured', function () {
    expect(class_exists(ServiceProviderResource::class))->toBeTrue();
    expect(ServiceProviderResource::getModel())->toBe(ServiceProvider::class);
    expect(ServiceProviderResource::getNavigationGroup())->toBe('Servicios');
});

// TEST 3: AppointmentResource is properly configured
it('AppointmentResource is properly configured', function () {
    expect(class_exists(AppointmentResource::class))->toBeTrue();
    expect(AppointmentResource::getModel())->toBe(Appointment::class);
    expect(AppointmentResource::getNavigationGroup())->toBe('Servicios');
});

// TEST 4: ServiceResource has required pages
it('ServiceResource has required pages', function () {
    $pages = ServiceResource::getPages();
    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});

// TEST 5: ServiceProviderResource has required pages
it('ServiceProviderResource has required pages', function () {
    $pages = ServiceProviderResource::getPages();
    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});

// TEST 6: AppointmentResource has required pages
it('AppointmentResource has required pages', function () {
    $pages = AppointmentResource::getPages();
    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});

// TEST 7: Service page classes exist
it('Service page classes exist', function () {
    expect(class_exists(\App\Filament\App\Resources\ServiceResource\Pages\ListServices::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\ServiceResource\Pages\CreateService::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\ServiceResource\Pages\EditService::class))->toBeTrue();
});

// TEST 8: ServiceProvider page classes exist
it('ServiceProvider page classes exist', function () {
    expect(class_exists(\App\Filament\App\Resources\ServiceProviderResource\Pages\ListServiceProviders::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\ServiceProviderResource\Pages\CreateServiceProvider::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\ServiceProviderResource\Pages\EditServiceProvider::class))->toBeTrue();
});

// TEST 9: Appointment page classes exist
it('Appointment page classes exist', function () {
    expect(class_exists(\App\Filament\App\Resources\AppointmentResource\Pages\ListAppointments::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\AppointmentResource\Pages\CreateAppointment::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\AppointmentResource\Pages\EditAppointment::class))->toBeTrue();
});
