<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Appointment Reminder Email (24h Before)
 *
 * Sent to customer_email 24 hours before the scheduled appointment.
 * Uses tenant branding and includes rebooking/cancellation links.
 *
 * INVARIANT: Must include tenant->name in subject for multi-tenant isolation.
 */
class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio de Cita - '.$this->appointment->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appointment.reminder',
            with: [
                'tenantName' => $this->appointment->tenant->name,
                'serviceName' => $this->appointment->service->name,
                'date' => $this->appointment->scheduled_at->translatedFormat('l, d \d\e F \d\e Y'),
                'time' => $this->appointment->scheduled_at->format('h:i A'),
                'providerName' => $this->appointment->provider->name,
                'managementUrl' => URL::signedRoute(
                    'public.appointment.manage',
                    ['appointment' => $this->appointment->id]
                ),
            ],
        );
    }
}
