<?php

declare(strict_types=1);

namespace App\Mail;

use App\Application\Booking\DTOs\AppointmentReminderData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class AppointmentReminderMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly AppointmentReminderData $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio de Cita - '.$this->reminder->tenantName,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appointment.reminder',
            with: [
                'tenantName' => $this->reminder->tenantName,
                'serviceName' => $this->reminder->serviceName,
                'date' => $this->reminder->date,
                'time' => $this->reminder->time,
                'providerName' => $this->reminder->providerName,
                'managementUrl' => $this->reminder->managementUrl,
            ],
        );
    }
}
