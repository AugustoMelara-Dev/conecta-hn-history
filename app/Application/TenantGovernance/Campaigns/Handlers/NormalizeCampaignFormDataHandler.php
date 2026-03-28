<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Campaigns\Handlers;

use App\Application\TenantGovernance\Campaigns\Commands\NormalizeCampaignFormDataCommand;
use App\Application\TenantGovernance\Contracts\TenantCampaignFinderInterface;
use App\Domain\TenantGovernance\Services\CampaignFormDataCoordinator;

final readonly class NormalizeCampaignFormDataHandler
{
    public function __construct(
        private TenantCampaignFinderInterface $campaigns,
        private CampaignFormDataCoordinator $coordinator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(NormalizeCampaignFormDataCommand $command): array
    {
        $record = $command->campaignId !== null
            ? $this->campaigns->findCampaign($command->tenantId, $command->campaignId)
            : null;

        return $this->coordinator->normalize($command->data, $record);
    }
}
