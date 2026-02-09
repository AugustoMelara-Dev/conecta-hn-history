<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AppointmentManagementController;
use App\Models\Appointment;
use Illuminate\Support\Facades\URL;

// ═══════════════════════════════════════════════════════════════════════
// GUEST PORTAL TESTS - Unit & Integration Tests
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: Controller class exists
it('AppointmentManagementController class exists', function () {
    expect(class_exists(AppointmentManagementController::class))->toBeTrue();
});

// TEST 2: Controller has required methods
it('AppointmentManagementController has required methods', function () {
    $methods = get_class_methods(AppointmentManagementController::class);
    
    expect($methods)->toContain('show');
    expect($methods)->toContain('cancel');
});

// TEST 3: Routes are defined
it('Guest portal routes are defined', function () {
    $routeFile = base_path('routes/public.php');
    $contents = file_get_contents($routeFile);
    
    expect($contents)->toContain('AppointmentManagementController');
    expect($contents)->toContain('public.appointment.manage');
    expect($contents)->toContain('public.appointment.cancel');
    expect($contents)->toContain("middleware('signed')");
});

// TEST 4: React page exists
it('Guest portal React page exists', function () {
    $pagePath = base_path('resources/js/Pages/Public/Appointment/Manage.jsx');
    
    expect(file_exists($pagePath))->toBeTrue();
});

// TEST 5: Email template includes management URL
it('Email template includes management button', function () {
    $templatePath = base_path('resources/views/emails/appointment/confirmation.blade.php');
    $contents = file_get_contents($templatePath);
    
    expect($contents)->toContain('$managementUrl');
    expect($contents)->toContain('Gestionar mi Cita');
});

// TEST 6: Mailable generates signed URL
it('Mailable includes managementUrl in content', function () {
    $mailablePath = base_path('app/Mail/AppointmentConfirmationMail.php');
    $contents = file_get_contents($mailablePath);
    
    expect($contents)->toContain('URL::signedRoute');
    expect($contents)->toContain('managementUrl');
    expect($contents)->toContain('public.appointment.manage');
});

// TEST 7: Appointment has cancellable statuses
it('Appointment has correct status constants', function () {
    expect(Appointment::STATUS_PENDING)->toBe('pending');
    expect(Appointment::STATUS_CONFIRMED)->toBe('confirmed');
    expect(Appointment::STATUS_CANCELLED)->toBe('cancelled');
});
