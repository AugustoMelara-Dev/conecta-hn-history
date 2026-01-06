<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiringMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public int $daysRemaining
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->daysRemaining === 1
            ? 'Tu Plan PRO vence MANANA | Conecta HN'
            : "Tu Plan PRO vence en {$this->daysRemaining} dias | Conecta HN";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-expiring',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
