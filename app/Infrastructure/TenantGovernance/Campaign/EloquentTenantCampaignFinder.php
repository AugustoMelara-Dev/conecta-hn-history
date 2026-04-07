<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Campaign;

use App\Application\TenantGovernance\Contracts\TenantCampaignFinderInterface;
use App\Models\Campaign;

final class EloquentTenantCampaignFinder implements TenantCampaignFinderInterface
{
    public function findCampaign(string $tenantId, int $campaignId): ?Campaign
    {
        return Campaign::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($campaignId);
    }
}
