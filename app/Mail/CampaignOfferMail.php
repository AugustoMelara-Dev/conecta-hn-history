<?php

declare(strict_types=1);

namespace App\Mail;

use App\Application\Shared\DTOs\Mail\CampaignMailData;
use App\Application\Shared\DTOs\Mail\UserMailData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * CampaignOfferMail - Refactored for Safe Serialization
 *
 * Uses CampaignMailData and UserMailData DTOs instead of Eloquent Models.
 */
class CampaignOfferMail extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly CampaignMailData $campaign,
        public readonly UserMailData $user
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
            from: new Address(
                config('mail.from.address'),
                $this->campaign->tenantName.' via Dovra AI'
            ),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign.offer',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
