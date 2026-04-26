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
 * Appointment Confirmation Email
 *
 * Sent to customer_email when a booking is created.
 * Uses tenant branding and includes booking details.
 */
class AppointmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación de Cita - '.$this->appointment->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appointment.confirmation',
            with: [
                'tenantName' => $this->appointment->tenant->name,
                'serviceName' => $this->appointment->service->name,
                'date' => $this->appointment->scheduled_at->format('d/m/Y h:i A'),
                'providerName' => $this->appointment->provider->name,
                'managementUrl' => URL::temporarySignedRoute(
                    'public.appointment.manage',
                    $this->managementUrlExpiresAt(),
                    ['appointment' => $this->appointment->id]
                ),
            ],
        );
    }

    private function managementUrlExpiresAt(): \DateTimeInterface
    {
        $expiresAt = $this->appointment->scheduled_at->copy()->addDay();

        return $expiresAt->greaterThan(now()->addDay()) ? $expiresAt : now()->addDay();
    }
}
