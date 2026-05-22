<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CustomerTask;
use Illuminate\Support\Facades\Schema;

final class EloquentTenantMicrositeReader
{
    public function visibleProductsCount(object $tenant): int
    {
        if (! Schema::hasTable('products') || ! method_exists($tenant, 'products')) {
            return 0;
        }

        return $tenant->products()
            // PUBLIC_READ_WITH_TENANT_FILTER: relationship is rooted in the public tenant before visibility filtering.
            ->withoutGlobalScopes()
            ->where('is_visible', true)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function crmData(object $tenant, bool $isOwner): array
    {
        $hasCrmFeature = method_exists($tenant, 'hasFeature')
            && method_exists($tenant, 'hasCapability')
            && $tenant->hasFeature(TenantFeature::MARKETING_SUITE)
            && $tenant->hasCapability(Capability::CRM_PIPELINE_MANAGEMENT);
        $passesRollout = $this->passesCrmRollout($tenant);
        $enabled = $hasCrmFeature && $passesRollout;

        $base = [
            'enabled' => $enabled,
            'feature_gate' => $hasCrmFeature,
            'rollout_gate' => $passesRollout,
            'is_owner' => $isOwner,
            'summary' => [
                'pipelines' => 0,
                'open_deals' => 0,
                'won_deals' => 0,
                'tasks_due' => 0,
            ],
            'pipelines' => [],
            'deals' => [],
            'tasks' => [],
        ];

        if (! $enabled || ! Schema::hasTable('crm_pipelines') || ! Schema::hasTable('crm_deals') || ! Schema::hasTable('customer_tasks')) {
            return $base;
        }

        $tenantId = (string) $tenant->getKey();

        $pipelines = CrmPipeline::query()
            // PUBLIC_READ_WITH_TENANT_FILTER: microsite CRM preview is gated and scoped to this public tenant.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->withCount('stages')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'is_default', 'is_active', 'sort_order']);

        $deals = CrmDeal::query()
            // PUBLIC_READ_WITH_TENANT_FILTER: microsite CRM preview is gated and scoped to this public tenant.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['pipeline:id,name', 'stage:id,name'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'crm_pipeline_id', 'crm_stage_id', 'title', 'status', 'value', 'currency', 'updated_at']);

        $tasks = CustomerTask::query()
            // PUBLIC_READ_WITH_TENANT_FILTER: microsite CRM preview is gated and scoped to this public tenant.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->orderBy('sla_due_at')
            ->orderBy('due_at')
            ->limit(8)
            ->get(['id', 'title', 'status', 'priority', 'task_type', 'sla_due_at', 'due_at']);

        return [
            'enabled' => true,
            'feature_gate' => true,
            'rollout_gate' => true,
            'is_owner' => $isOwner,
            'summary' => [
                // PUBLIC_READ_WITH_TENANT_FILTER: all summary counts below include explicit tenant predicates.
                'pipelines' => CrmPipeline::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
                'open_deals' => CrmDeal::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', CrmDeal::STATUS_OPEN)->count(),
                'won_deals' => CrmDeal::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', CrmDeal::STATUS_WON)->count(),
                // PUBLIC_READ_WITH_TENANT_FILTER: due task count is tenant-scoped before status/date filtering.
                'tasks_due' => CustomerTask::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('status', ['open', 'in_progress'])->where(function ($query): void {
                    $query->whereNotNull('due_at')->where('due_at', '<=', now()->endOfDay());
                })->count(),
            ],
            'pipelines' => $pipelines->map(fn (CrmPipeline $pipeline): array => [
                'id' => $pipeline->getKey(),
                'name' => $pipeline->name,
                'is_default' => (bool) $pipeline->is_default,
                'is_active' => (bool) $pipeline->is_active,
                'stages_count' => (int) $pipeline->stages_count,
            ])->values()->all(),
            'deals' => $deals->map(fn (CrmDeal $deal): array => [
                'id' => $deal->getKey(),
                'title' => $deal->title,
                'status' => $deal->status,
                'value' => $deal->value,
                'currency' => $deal->currency ?: 'HNL',
                'pipeline' => $deal->pipeline?->name,
                'stage' => $deal->stage?->name,
                'updated_at' => optional($deal->updated_at)?->toIso8601String(),
            ])->values()->all(),
            'tasks' => $tasks->map(fn (CustomerTask $task): array => [
                'id' => $task->getKey(),
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'task_type' => $task->task_type,
                'sla_due_at' => optional($task->sla_due_at)?->toIso8601String(),
                'due_at' => optional($task->due_at)?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function passesCrmRollout(object $tenant): bool
    {
        if (! config('microsite-crm.enabled', false)) {
            return false;
        }

        $allowlist = collect((array) config('microsite-crm.allowlist', []))
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->values();

        $tenantId = (string) $tenant->getKey();
        $tenantSlug = (string) ($tenant->slug ?? '');

        if ($allowlist->contains($tenantId) || $allowlist->contains($tenantSlug)) {
            return true;
        }

        $percentage = max(0, min(100, (int) config('microsite-crm.percentage', 0)));

        return $percentage > 0 && (abs(crc32($tenantId ?: $tenantSlug)) % 100) < $percentage;
    }
}
