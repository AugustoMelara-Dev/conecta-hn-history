<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CampaignRepositoryInterface;
use App\Infrastructure\TenantGovernance\Campaign\TenantCampaignWorkspaceReader;
use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

final class TenantCampaignDispatchPlanner
{
    public function __construct(
        private readonly TenantCampaignWorkspaceReader $workspaceReader,
        private readonly CampaignRepositoryInterface $campaigns,
    ) {}

    /**
     * @return array{audience_count:int,next_run_at:?Carbon,uses_cadence:bool}
     */
    public function planDispatch(Tenant $tenant, Campaign $campaign): array
    {
        $audienceCount = $this->workspaceReader->calculateAudienceCount($tenant, $campaign);
        $usesCadence = $campaign->usesCadence();
        $nextRunAt = $usesCadence
            ? ($campaign->next_run_at !== null && $campaign->next_run_at->isFuture()
                ? Carbon::instance($campaign->next_run_at)
                : now()->addDays((int) $campaign->cadence_interval_days))
            : null;

        return [
            'audience_count' => $audienceCount,
            'next_run_at' => $nextRunAt,
            'uses_cadence' => $usesCadence,
        ];
    }

    public function queueCampaign(Campaign $campaign, ?Carbon $nextRunAt = null): Campaign
    {
        return $this->campaigns->markQueued($campaign, $nextRunAt);
    }
}
