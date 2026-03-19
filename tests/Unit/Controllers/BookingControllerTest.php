<?php

declare(strict_types=1);

use App\Http\Controllers\Public\BookingController;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;

// ═══════════════════════════════════════════════════════════════════════
// BOOKING UNIT TESTS - Controller and Model Configuration
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: BookingController exists
it('BookingController class exists', function () {
    expect(class_exists(BookingController::class))->toBeTrue();
});

// TEST 2: BookingController has required methods
it('BookingController has required methods', function () {
    $methods = get_class_methods(BookingController::class);

    expect($methods)->toContain('index');
    expect($methods)->toContain('store');
    expect($methods)->toContain('getProviders');
    expect($methods)->toContain('getSlots');
    expect($methods)->toContain('success');
});

// TEST 3: Tenant has services relationship
it('Tenant model has services relationship', function () {
    expect((new ReflectionClass(Tenant::class))->hasMethod('services'))->toBeTrue();
});

// TEST 4: Tenant has serviceProviders relationship
it('Tenant model has serviceProviders relationship', function () {
    expect((new ReflectionClass(Tenant::class))->hasMethod('serviceProviders'))->toBeTrue();
});

// TEST 5: Tenant has appointments relationship
it('Tenant model has appointments relationship', function () {
    expect((new ReflectionClass(Tenant::class))->hasMethod('appointments'))->toBeTrue();
});

// TEST 6: Service model has active scope
it('Service model has active scope', function () {
    expect((new ReflectionClass(Service::class))->hasMethod('scopeActive'))->toBeTrue();
});

// TEST 7: ServiceProvider model has active scope
it('ServiceProvider model has active scope', function () {
    expect((new ReflectionClass(ServiceProvider::class))->hasMethod('scopeActive'))->toBeTrue();
});
