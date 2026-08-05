<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);

// ═══════════════════════════════════════════════════════════════════════
// SERVICE DOMAIN TESTS - Model Configuration and Relationships
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: Service model exists and has correct casts
it('Service model has correct casts', function () {
    $casts = (new Service)->getCasts();
    
    expect($casts)->toHaveKey('price');
    expect($casts)->toHaveKey('price_minor');
    expect($casts)->toHaveKey('is_active');
    expect($casts)->toHaveKey('duration_minutes');
});

// TEST 2: ServiceProvider model exists and has correct casts
it('ServiceProvider model has correct casts', function () {
    $casts = (new ServiceProvider)->getCasts();
    
    expect($casts)->toHaveKey('schedule_config');
    expect($casts)->toHaveKey('is_active');
});

// TEST 3: Appointment model has correct casts
it('Appointment model casts scheduled_at to datetime', function () {
    $casts = (new Appointment)->getCasts();
    
    expect($casts['scheduled_at'])->toBe('datetime');
    expect($casts['end_at'])->toBe('datetime');
});

// TEST 4: Appointment has status constants
it('Appointment has status constants', function () {
    expect(Appointment::STATUS_PENDING)->toBe('pending');
    expect(Appointment::STATUS_CONFIRMED)->toBe('confirmed');
    expect(Appointment::STATUS_CANCELLED)->toBe('cancelled');
    expect(Appointment::STATUS_COMPLETED)->toBe('completed');
});

// TEST 5: Service has formatted duration accessor
it('Service has formatted duration accessor', function () {
    $service = new Service(['duration_minutes' => 90]);
    
    expect($service->formatted_duration)->toBe('1h 30min');
});

// TEST 6: Service has formatted price accessor
it('Service has formatted price accessor', function () {
    $service = new Service(['price' => 250.50, 'price_minor' => 25050, 'currency' => 'CAD']);
    
    expect($service->formatted_price)->toBe('CAD 250.50');
    expect((new Service(['price' => 250.50]))->formatted_price)->toBe('250.50');
});

// TEST 7: Appointment has status label accessor
it('Appointment has status label in Spanish', function () {
    $appointment = new Appointment(['status' => 'pending']);
    expect($appointment->status_label)->toBe('Pendiente');
    
    $appointment->status = 'confirmed';
    expect($appointment->status_label)->toBe('Confirmada');
});

// TEST 8: Models use BelongsToTenant trait
it('Service domain models use BelongsToTenant trait', function () {
    $serviceTraits = class_uses_recursive(Service::class);
    $providerTraits = class_uses_recursive(ServiceProvider::class);
    $appointmentTraits = class_uses_recursive(Appointment::class);
    
    expect($serviceTraits)->toContain('App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant');
    expect($providerTraits)->toContain('App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant');
    expect($appointmentTraits)->toContain('App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant');
});
