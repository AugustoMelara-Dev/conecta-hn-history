<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Shared\Contracts\AppointmentRepositoryInterface;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

        if (! $appointment) {
            abort(404, 'Appointment not found.');
        }

        $service = $appointment->service;
        $provider = $appointment->provider;
        /** @var Tenant $tenant */
        $tenant = $appointment->tenant;

        return Inertia::render('Public/Appointment/Manage', [
            'appointment' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'customer_name' => $appointment->customer_name,
                'service_name' => $service?->name ?? 'Servicio',
                'provider_name' => $provider?->name ?? 'Proveedor',
                'scheduled_at' => $appointment->scheduled_at->format('l, d M Y'),
                'time' => $appointment->scheduled_at->format('h:i A'),
                'can_cancel' => in_array($appointment->status, ['pending', 'confirmed']),
                'cancel_url' => URL::temporarySignedRoute(
                    'public.appointment.cancel',
                    $this->signedUrlExpiresAt($request, $appointment->scheduled_at),
                    ['appointment' => $appointment->id]
                ),
            ],
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo_url' => $tenant->logo_path ? Storage::url($tenant->logo_path) : null,
                'microsite_url' => route('tenant.microsite.show', ['tenant' => $tenant], false),
            ],
        ]);
    }

    /**
     * Cancel the appointment.
     *
     * Guards against invalid state transitions (completed/cancelled appointments).
     * Idempotent: safe for double-click scenarios.
     */
    public function cancel(Request $request, string $appointmentId): RedirectResponse
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

    private function signedUrlExpiresAt(Request $request, \DateTimeInterface $fallbackBase): \DateTimeInterface
    {
        $expires = $request->query('expires');
        if (is_numeric($expires) && (int) $expires > now()->timestamp) {
            return now()->setTimestamp((int) $expires);
        }

        $fallback = \Illuminate\Support\Carbon::parse($fallbackBase->format(DATE_ATOM));

        return $fallback->greaterThan(now()) ? $fallback : now()->addHours(12);
    }
}
