<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\CustomerTaskRepositoryInterface;
use App\Models\CustomerTask;

final class EloquentCustomerTaskRepository implements CustomerTaskRepositoryInterface
{
    public function firstOrNewByNote(string $tenantId, int|string $noteId): CustomerTask
    {
        return CustomerTask::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'tenant_id' => $tenantId,
                'customer_note_id' => (int) $noteId,
            ]);
    }

    public function firstOpenJourneyRescueTask(string $tenantId, int|string $conversationId): CustomerTask
    {
        return CustomerTask::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'tenant_id' => $tenantId,
                'customer_conversation_id' => (int) $conversationId,
                'task_type' => 'journey_rescue',
                'status' => 'open',
            ]);
    }

    public function save(CustomerTask $task): CustomerTask
    {
        $task->save();

        return $task->fresh();
    }
}
