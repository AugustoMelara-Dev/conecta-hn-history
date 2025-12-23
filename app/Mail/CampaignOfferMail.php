<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignOfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public $campaign;

    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(Campaign $campaign, User $user)
    {
        $this->campaign = $campaign;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                $this->campaign->tenant->name.' via Conecta HN'
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
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
