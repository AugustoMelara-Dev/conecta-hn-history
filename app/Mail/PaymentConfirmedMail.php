<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment,
        public Tenant $tenant
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pago Confirmado - Tu Plan PRO esta Activo | Conecta HN',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-confirmed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

