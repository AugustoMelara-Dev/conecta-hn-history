<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\Campaign;
use Illuminate\Support\Carbon;

interface CampaignRepositoryInterface
{
    public function markQueued(Campaign $campaign, ?Carbon $nextRunAt = null): Campaign;
}
