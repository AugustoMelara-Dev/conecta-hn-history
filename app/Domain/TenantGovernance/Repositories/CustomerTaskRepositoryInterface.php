<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\CustomerTask;

interface CustomerTaskRepositoryInterface
{
    public function firstOrNewByNote(string $tenantId, int|string $noteId): CustomerTask;

    public function firstOpenJourneyRescueTask(string $tenantId, int|string $conversationId): CustomerTask;

    public function save(CustomerTask $task): CustomerTask;
}
