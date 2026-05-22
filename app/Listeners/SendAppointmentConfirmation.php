<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Appointment\AppointmentCreated;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Sends confirmation email when an appointment is created.
 *
 * QUEUED: Implements ShouldQueue to prevent blocking HTTP requests.
 *
 * INVARIANT: Only sends if customer_email is present.
 * Walk-ins (no email) are silently skipped.
 */
final class SendAppointmentConfirmation implements ShouldQueue
{
    public function handle(AppointmentCreated $event): void
    {
        $appointment = Appointment::query()
            ->where('id', $event->appointmentId)
            ->where('tenant_id', (string) $event->tenantKey)
            ->first();

        if (! $appointment) {
            return;
        }

        // Skip if no email (walk-in or guest without email)
        if (empty($appointment->customer_email)) {
            return;
        }

        // Ensure relationships are loaded for the email template
        $appointment->loadMissing(['tenant', 'service', 'provider']);

        Mail::to($appointment->customer_email)
            ->send(new AppointmentConfirmationMail($appointment));
    }
}
