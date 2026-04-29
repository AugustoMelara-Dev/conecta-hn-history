<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ReleaseAlertMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly array $context,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Alerta critica de release - '.$this->context['feature_key'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.release-alert',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
