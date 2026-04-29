<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpMail extends Mailable
{
    use Queueable;

    public string $code;

    public int $expiresInMinutes;

    public ?string $magicUrl;

    public string $resetPasswordUrl;

    public function __construct(
        string $code,
        int $expiresInMinutes = 10,
        ?string $magicUrl = null,
        ?string $resetPasswordUrl = null
    ) {
        $this->code = $code;
        $this->expiresInMinutes = $expiresInMinutes;
        $this->magicUrl = $magicUrl;
        $this->resetPasswordUrl = $resetPasswordUrl ?? route('password.request');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código de acceso - Vito Business OS',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
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
