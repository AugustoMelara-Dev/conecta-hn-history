<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class WaitlistSlotFreedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $customerName,
        public readonly string $tenantName,
        public readonly string $serviceName,
        public readonly string $date,
        public readonly string $bookingUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Un espacio se ha liberado en '.$this->tenantName.'!',
            from: new Address(
                config('mail.from.address'),
                $this->tenantName.' via Conecta HN'
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking.waitlist_slot_freed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
