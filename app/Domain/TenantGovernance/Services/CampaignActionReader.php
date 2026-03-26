<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Campaign;
use App\Models\Tenant;

final class CampaignActionReader
{
    public function __construct(
        private readonly TenantCampaignPresentationReader $presentationReader,
        private readonly TenantCampaignActionCoordinator $actionCoordinator,
    ) {}

    public function dispatchLabel(Campaign $campaign): string
    {
        return $this->presentationReader->dispatchLabel($campaign);
    }

    public function canDispatch(Campaign $campaign): bool
    {
        return $this->presentationReader->canDispatch($campaign);
    }

    public function dispatchDescription(Campaign $campaign): string
    {
        return $this->presentationReader->dispatchDescription($campaign);
    }

    /**
     * @return array{status:string,title:string,body:string}|null
     */
    public function dispatch(?Tenant $tenant, Campaign $campaign): ?array
    {
        if (! $tenant instanceof Tenant) {
            return null;
        }

        return $this->actionCoordinator->dispatch($tenant, $campaign);
    }
}
