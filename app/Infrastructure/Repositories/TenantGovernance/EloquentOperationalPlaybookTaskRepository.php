<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\OperationalPlaybookTaskRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\OperationalPlaybookTask;

final class EloquentOperationalPlaybookTaskRepository implements OperationalPlaybookTaskRepositoryInterface
{
    public function findOpenTask(
        string $tenantId,
        string $triggerKey,
        ?string $relatedType,
        ?string $relatedId,
    ): ?OperationalPlaybookTask {
        $query = OperationalPlaybookTask::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('trigger_key', $triggerKey)
            ->whereIn('status', [
                OperationalPlaybookTask::STATUS_OPEN,
                OperationalPlaybookTask::STATUS_IN_PROGRESS,
            ]);

        if ($relatedType !== null && $relatedId !== null) {
            $query
                ->where('related_type', $relatedType)
                ->where('related_id', $relatedId);
        } else {
            $query
                ->whereNull('related_type')
                ->whereNull('related_id');
        }

        return $query->latest('created_at')->first();
    }

    public function save(OperationalPlaybookTask $task): void
    {
        $task->save();
    }
}
