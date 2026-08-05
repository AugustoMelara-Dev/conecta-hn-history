<?php

declare(strict_types=1);

namespace App\Infrastructure\Booking;

// @withoutGlobalScopes-safety INFRA_TENANT_FILTER: guest lookup is constrained by tenant-owned signed appointment context.

use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\TransitionReservationCommand;
use App\Models\Appointment;
use Illuminate\Support\Facades\Storage;

final class GuestAppointmentManagementReader
{
    public function __construct(private readonly CanonicalReservationWriter $writer) {}

    /**
     * @return array{appointment: array<string, mixed>, tenant: array<string, mixed>}
     */
    public function pageData(int|string $appointmentId): array
    {
        $appointment = Appointment::query()
            ->withoutGlobalScopes()
            ->with(['tenant', 'service', 'provider'])
            ->findOrFail($appointmentId);

        return [
            'appointment' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'customer_name' => $appointment->customer_name,
                'service_name' => $appointment->service_name_snapshot ?: $appointment->service->name,
                'provider_name' => $appointment->provider->name,
                'scheduled_at' => $appointment->scheduled_at->format('l, d M Y'),
                'time' => $appointment->scheduled_at->format('h:i A'),
                'can_cancel' => in_array($appointment->status, ['pending', 'confirmed'], true),
            ],
            'tenant' => [
                'name' => $appointment->tenant->name,
                'slug' => $appointment->tenant->slug,
                'logo_url' => $appointment->tenant->logo_path
                    ? Storage::url($appointment->tenant->logo_path)
                    : null,
            ],
        ];
    }

    public function cancel(int|string $appointmentId): bool
    {
        $appointment = Appointment::query()
            ->withoutGlobalScopes()
            ->findOrFail($appointmentId);

        if (! in_array($appointment->status, ['pending', 'confirmed'], true)) {
            return false;
        }

        $this->writer->cancel(new TransitionReservationCommand(
            tenantId: (string) $appointment->tenant_id,
            actorId: null,
            appointmentId: (int) $appointment->getKey(),
            targetStatus: Appointment::STATUS_CANCELLED,
            reason: 'Guest cancellation from signed management link',
            guestInitiated: true,
        ));

        return true;
    }
}
