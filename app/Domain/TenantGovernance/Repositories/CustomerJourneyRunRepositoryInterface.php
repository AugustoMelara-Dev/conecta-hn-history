<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\CustomerJourneyRun;
use Illuminate\Support\Collection;

interface CustomerJourneyRunRepositoryInterface
{
    public function firstOrNewByConversation(int $conversationId): CustomerJourneyRun;

    /**
     * @return Collection<int, CustomerJourneyRun>
     */
    public function dueRuns(?string $tenantId = null, int $limit = 50): Collection;

    public function save(CustomerJourneyRun $run): CustomerJourneyRun;
}
