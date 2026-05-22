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
            id: (int) $campaign->id,
            subject: (string) ($campaign->subject ?? ''),
            body: (string) ($campaign->body ?? ''),
            tenantName: (string) ($campaign->tenant?->name ?? 'Vito Business OS'),
        );
    }
}
