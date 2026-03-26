<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\CampaignRepositoryInterface;
use App\Models\Campaign;
use Illuminate\Support\Carbon;

final class EloquentCampaignRepository implements CampaignRepositoryInterface
{
    public function markQueued(Campaign $campaign, ?Carbon $nextRunAt = null): Campaign
    {
        $campaign->update([
            'status' => 'queued',
            'next_run_at' => $nextRunAt,
        ]);

        return $campaign->refresh();
    }
}
