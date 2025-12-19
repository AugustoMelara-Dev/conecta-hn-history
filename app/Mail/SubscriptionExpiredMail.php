<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu Plan PRO ha sido Suspendido | Conecta HN',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-expired',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

