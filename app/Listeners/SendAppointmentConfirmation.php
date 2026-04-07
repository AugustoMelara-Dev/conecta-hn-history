<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Appointment\AppointmentCreated;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

/**
 * Sends confirmation email when an appointment is created.
 *
 * QUEUED: Implements ShouldQueue to prevent blocking HTTP requests.
 *
 * INVARIANT: Only sends if customer_email is present.
 * Walk-ins (no email) are silently skipped.
 */
class SendAppointmentConfirmation implements ShouldQueue, ShouldQueueAfterCommit
{
    public function shouldQueue(AppointmentCreated $event): bool
    {
        return ! config('app.e2e_browser_release');
    }

    public function handle(AppointmentCreated $event): void
    {
        if (config('app.e2e_browser_release')) {
            return;
        }

        $appointmentId = $event->appointment->getKey();
        $appointment = Appointment::query()
            ->with(['tenant', 'service', 'provider'])
            ->find($appointmentId);

        if (! $appointment || empty($appointment->customer_email)) {
            return;
        }

        Mail::to($appointment->customer_email)
            ->send(new AppointmentConfirmationMail($appointment));
    }
}
