<?php

declare(strict_types=1);

namespace App\Mail;

use App\Application\Shared\DTOs\Mail\UserMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * WelcomeUserMail - Refactored for Safe Serialization
 *
 * Uses UserMailData DTO instead of Eloquent Model to avoid
 * SerializesModels race conditions in queued mail.
 */
class WelcomeUserMail extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly UserMailData $user
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenido a Conecta HN - Tu Infraestructura Digital',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
