<?php

declare(strict_types=1);

namespace App\Mail;

use App\Application\Shared\DTOs\Mail\PaymentMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * PaymentRejectedMail - Refactored for Safe Serialization
 *
 * Uses PaymentMailData DTO instead of Eloquent Model.
 */
class PaymentRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PaymentMailData $payment,
        public readonly string $reason
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pago No Aprobado - Accion Requerida | Vito Business OS',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-rejected',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
