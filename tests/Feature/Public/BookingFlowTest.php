<?php

declare(strict_types=1);

use App\Http\Controllers\Public\BookingController;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════
// BOOKING FLOW TESTS - Unit Tests (No DB Required)
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: BookingController class exists
it('BookingController class exists', function () {
    expect(class_exists(BookingController::class))->toBeTrue();
});

// TEST 2: BookingController has all required methods
it('BookingController has all required methods', function () {
    $methods = get_class_methods(BookingController::class);
    
    expect($methods)->toContain('index');
    expect($methods)->toContain('store');
    expect($methods)->toContain('getProviders');
    expect($methods)->toContain('getSlots');
    expect($methods)->toContain('success');
});

// TEST 3: Tenant model has services relationship
it('Tenant model has services relationship', function () {
    expect(method_exists(Tenant::class, 'services'))->toBeTrue();
});

// TEST 4: Tenant model has serviceProviders relationship
it('Tenant model has serviceProviders relationship', function () {
    expect(method_exists(Tenant::class, 'serviceProviders'))->toBeTrue();
});

// TEST 5: Tenant model has appointments relationship
it('Tenant model has appointments relationship', function () {
    expect(method_exists(Tenant::class, 'appointments'))->toBeTrue();
});

// TEST 6: Service model has active scope
it('Service model has active scope', function () {
    expect(method_exists(Service::class, 'scopeActive'))->toBeTrue();
});

// TEST 7: ServiceProvider model has active scope  
it('ServiceProvider model has active scope', function () {
    expect(method_exists(ServiceProvider::class, 'scopeActive'))->toBeTrue();
});

// TEST 8: ServiceProvider has isAvailableAt method
it('ServiceProvider has isAvailableAt method', function () {
    expect(method_exists(ServiceProvider::class, 'isAvailableAt'))->toBeTrue();
});

// TEST 9: Appointment model has status constants
it('Appointment model has status constants', function () {
    expect(Appointment::STATUS_PENDING)->toBe('pending');
    expect(Appointment::STATUS_CONFIRMED)->toBe('confirmed');
    expect(Appointment::STATUS_CANCELLED)->toBe('cancelled');
    expect(Appointment::STATUS_COMPLETED)->toBe('completed');
});

// TEST 10: React pages exist
it('React booking pages exist', function () {
    $indexPath = base_path('resources/js/Pages/Public/Book/Index.jsx');
    $successPath = base_path('resources/js/Pages/Public/Book/Success.jsx');
    
    expect(file_exists($indexPath))->toBeTrue();
    expect(file_exists($successPath))->toBeTrue();
});

// TEST 11: Booking routes are registered on the tenant subdomain
it('Booking routes are registered on the tenant subdomain', function () {
    $routes = [
        'public.booking.index' => ['method' => 'GET', 'uri' => 'book', 'action' => 'index'],
        'public.booking.store' => ['method' => 'POST', 'uri' => 'book', 'action' => 'store'],
        'public.booking.providers' => ['method' => 'GET', 'uri' => 'book/providers/{service}', 'action' => 'getProviders'],
        'public.booking.slots' => ['method' => 'GET', 'uri' => 'book/slots', 'action' => 'getSlots'],
        'public.booking.success' => ['method' => 'GET', 'uri' => 'book/success', 'action' => 'success'],
    ];

    foreach ($routes as $name => $expected) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->methods())->toContain($expected['method'])
            ->and($route->uri())->toBe($expected['uri'])
            ->and($route->getDomain())->toContain('{tenant}')
            ->and($route->getActionName())->toBe(BookingController::class.'@'.$expected['action']);
    }
});
