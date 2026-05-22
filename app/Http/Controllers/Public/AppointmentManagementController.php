<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Infrastructure\Booking\GuestAppointmentManagementReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest Appointment Management Controller
 *
 * Handles secure, unauthenticated access to appointment details via signed URLs.
 * Used by guests to view and cancel their bookings without requiring an account.
 */
final class AppointmentManagementController extends Controller
{
    public function __construct(
        private readonly GuestAppointmentManagementReader $appointments,
    ) {}

    /**
     * Display the appointment management page.
     *
     * Security: 'signed' middleware verifies the URL signature before this runs.
     */
    public function show(Request $request, mixed $appointment): Response
    {
        return Inertia::render('Public/Appointment/Manage', $this->appointments->pageData($this->appointmentKey($appointment)));
    }

    /**
     * Cancel the appointment.
     *
     * Guards against invalid state transitions (completed/cancelled appointments).
     * Idempotent: safe for double-click scenarios.
     */
    public function cancel(Request $request, mixed $appointment)
    {
        if (! $this->appointments->cancel($this->appointmentKey($appointment))) {
            return back()->with('error', 'Esta cita no se puede cancelar porque ya fue completada o cancelada.');
        }

        return back()->with('success', 'Tu cita ha sido cancelada exitosamente.');
    }

    private function appointmentKey(mixed $appointment): int|string
    {
        return is_object($appointment) && method_exists($appointment, 'getKey')
            ? $appointment->getKey()
            : $appointment;
    }
}
