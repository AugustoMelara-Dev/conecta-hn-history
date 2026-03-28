<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Models\Campaign;

interface TenantCampaignFinderInterface
{
    public function findCampaign(string $tenantId, int $campaignId): ?Campaign;
}
