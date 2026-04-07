<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerLifecycleMail extends Mailable
{
    use Queueable;

    /**
     * @param  array{customer_name: string, intro: string, body: string, cta_label: string|null, cta_url: string|null, tenant_name: string}  $payload
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly array $payload,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            from: new Address(
                config('mail.from.address'),
                $this->payload['tenant_name'].' via Conecta HN',
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.customer.lifecycle',
            with: $this->payload,
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
