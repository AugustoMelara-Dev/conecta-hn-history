<?php

declare(strict_types=1);

it('keeps public guest and filament reservation surfaces on the canonical writer', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $surfaces = [
        '/Infrastructure/Booking/HttpPublicBookingResponder.php',
        '/Infrastructure/Booking/GuestAppointmentManagementReader.php',
        '/Filament/App/Resources/AppointmentResource.php',
        '/Filament/App/Resources/AppointmentResource/Pages/CreateAppointment.php',
        '/Filament/App/Resources/AppointmentResource/Pages/EditAppointment.php',
    ];

    foreach ($surfaces as $surface) {
        $contents = (string) file_get_contents($root.$surface);
        expect($contents)
            ->not->toContain('new Appointment')
            ->not->toContain('$appointment->update(')
            ->not->toContain('$record->confirm(')
            ->not->toContain('$record->complete(')
            ->not->toContain('DeleteAction::make(')
            ->not->toContain('DeleteBulkAction::make(');
    }

    expect((string) file_get_contents($root.'/Infrastructure/Booking/HttpPublicBookingResponder.php'))
        ->toContain('CanonicalReservationWriter')
        ->and((string) file_get_contents($root.'/Infrastructure/Booking/GuestAppointmentManagementReader.php'))
        ->toContain('CanonicalReservationWriter')
        ->and((string) file_get_contents($root.'/Filament/App/Resources/AppointmentResource/Pages/CreateAppointment.php'))
        ->toContain('CanonicalReservationWriter')
        ->not->toContain('Str::uuid()');
});

it('blocks direct appointment lifecycle persistence outside canonical and derived reminder owners', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $appointment = (string) file_get_contents($root.'/Models/Appointment.php');
    $writer = (string) file_get_contents($root.'/Application/Reservations/CanonicalReservationWriter.php');

    expect($appointment)
        ->not->toContain('return $this->save()')
        ->and($writer)
        ->toContain('new Appointment')
        ->toContain('AvailabilityQuery')
        ->toContain('lockForUpdate()');
});
