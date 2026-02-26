<?php

declare(strict_types=1);

namespace App\Application\Shared\DTOs\Mail;

/**
 * CampaignMailData - Immutable DTO for Campaign Email Context
 *
 * Captures campaign data at dispatch time to avoid SerializesModels.
 */
final readonly class CampaignMailData
{
    public function __construct(
        public int $id,
        public string $subject,
        public string $body,
        public string $tenantName,
    ) {}

    /**
     * Create from Campaign model (snapshot at dispatch time).
     */
    public static function fromCampaign(object $campaign): self
    {
        return new self(
            id: $campaign->id,
            subject: $campaign->subject ?? '',
            body: $campaign->body ?? '',
            tenantName: $campaign->tenant?->name ?? 'Conecta HN',
        );
    }
}
