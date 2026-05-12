<?php

declare(strict_types=1);

namespace App\Mail;

use App\Application\Shared\DTOs\Mail\TenantMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * SubscriptionExpiredMail - Refactored for Safe Serialization
 *
 * Uses TenantMailData DTO instead of Eloquent Model.
 */
class SubscriptionExpiredMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TenantMailData $tenant
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu Plan PRO ha sido suspendido | Vito Business OS',
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
