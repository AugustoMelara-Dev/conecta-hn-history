<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Campaigns\Commands;

final readonly class NormalizeCampaignFormDataCommand
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $tenantId,
        public array $data,
        public ?int $campaignId = null,
    ) {}
}
