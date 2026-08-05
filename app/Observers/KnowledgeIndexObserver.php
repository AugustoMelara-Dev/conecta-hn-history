<?php

declare(strict_types=1);

namespace App\Observers;

use App\Infrastructure\Jobs\AI\IndexTenantKnowledgeJob;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

final class KnowledgeIndexObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof Tenant
            && ! array_key_exists('created_at', $model->getChanges())
            && ! $model->wasChanged([
                'name',
                'description',
                'is_active',
                'subscription_status',
            ])) {
            return;
        }

        $this->dispatch($model);
    }

    public function deleted(Model $model): void
    {
        $this->dispatch($model);
    }

    public function restored(Model $model): void
    {
        $this->dispatch($model);
    }

    public function forceDeleted(Model $model): void
    {
        if ($model instanceof Tenant) {
            return;
        }

        $this->dispatch($model);
    }

    private function dispatch(Model $model): void
    {
        if (! config('ai.knowledge.enabled', false)) {
            return;
        }

        $tenantId = $model instanceof Tenant
            ? (string) $model->getKey()
            : (string) $model->getAttribute('tenant_id');

        if ($tenantId !== '') {
            IndexTenantKnowledgeJob::dispatch($tenantId)->afterCommit();
        }
    }
}
