<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Application\Shared\Contracts\AppointmentRepositoryInterface;
use App\Application\Shared\Contracts\CommandBusInterface;

/**
 * Guest Appointment Management Controller
 *
 * Handles secure, unauthenticated access to appointment details via signed URLs.
 * Used by guests to view and cancel their bookings without requiring an account.
 */
final class AppointmentManagementController extends Controller
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private CommandBusInterface $commandBus
    ) {}
    /**
     * Display the appointment management page.
     *
     * Security: 'signed' middleware verifies the URL signature before this runs.
     */
    public function show(Request $request, string $appointmentId): Response
    {
        $appointment = $this->appointmentRepository->findWithRelations($appointmentId);

        if (!$appointment) {
            abort(404, 'Appointment not found.');
        }

        return Inertia::render('Public/Appointment/Manage', [
            'appointment' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'customer_name' => $appointment->customer_name,
                'service_name' => $appointment->service->name,
                'provider_name' => $appointment->provider->name,
                'scheduled_at' => $appointment->scheduled_at->format('l, d M Y'),
                'time' => $appointment->scheduled_at->format('h:i A'),
                'can_cancel' => in_array($appointment->status, ['pending', 'confirmed']),
            ],
            'tenant' => [
                'name' => $appointment->tenant->name,
                'slug' => $appointment->tenant->slug,
                'logo_url' => $appointment->tenant->logo_url,
            ],
        ]);
    }

    /**
     * Cancel the appointment.
     *
     * Guards against invalid state transitions (completed/cancelled appointments).
     * Idempotent: safe for double-click scenarios.
     */
    public function cancel(Request $request, string $appointmentId)
    {
        $appointment = $this->appointmentRepository->findById($appointmentId);

        if (! $appointment) {
            abort(404, 'Appointment not found.');
        }

        if (! in_array($appointment->status, ['pending', 'confirmed'])) {
            return back()->with('error', 'Esta cita no se puede cancelar porque ya fue completada o cancelada.');
        }

        // Dispatch a Command to mutate state (CQRS compliant)
        $this->commandBus->dispatch(new \App\Application\Tenant\Commands\CancelAppointmentCommand($appointment->id));

        // Future: Dispatch AppointmentCancelled event for notifications

        return back()->with('success', 'Tu cita ha sido cancelada exitosamente.');
    }
}
