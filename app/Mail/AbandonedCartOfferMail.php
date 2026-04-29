<?php

declare(strict_types=1);

namespace App\Mail;

use App\Application\Shared\DTOs\Mail\UserMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class AbandonedCartOfferMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly UserMailData $user,
        public readonly string $tenantName,
        public readonly string $couponCode,
        public readonly string $discountText,
        public readonly float $subtotal
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Olvidaste algo en tu carrito...',
            from: new Address(
                config('mail.from.address'),
                $this->tenantName.' via Vito Business OS'
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign.abandoned_cart',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
