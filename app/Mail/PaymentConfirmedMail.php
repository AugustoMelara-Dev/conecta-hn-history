<?php

declare(strict_types=1);

namespace App\Mail;

use App\Application\Shared\DTOs\Mail\PaymentMailData;
use App\Application\Shared\DTOs\Mail\TenantMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * PaymentConfirmedMail - Refactored for Safe Serialization
 *
 * Uses PaymentMailData and TenantMailData DTOs instead of Eloquent Models.
 */
class PaymentConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PaymentMailData $payment,
        public readonly TenantMailData $tenant
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
