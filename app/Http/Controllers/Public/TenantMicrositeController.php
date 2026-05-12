<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Tenant\Transformers\TenantMicrositeTransformer;
use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Domain\Tenant\Services\TenantVisibilityService;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CustomerTask;
use App\Models\Tenant;
// Assumed Event exists or will be created
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TenantMicrositeController - Clean Read-Only
 *
 * ARCHITECTURE:
 * - Pure Read: No side-effects (Event Driven Views).
 * - Logic-Free: Queries moved to Repository.
 * - Gatekeeper: Visibility rules in Service.
 */
final class TenantMicrositeController extends Controller
{
    public function __construct(
        private readonly TenantStorefrontRepository $repository,
        private readonly TenantMicrositeTransformer $transformer,
        private readonly PlanFeatureFlagService $planService,
        private readonly TenantVisibilityService $visibilityService
    ) {}

    public function show(Request $request): Response
    {
        if ($this->isDirectoryAppHost($request)) {
            return app(DirectoryController::class)->index($request);
        }

        // 1. Resolve Tenant from Container (set by IdentifyTenantBySubdomain middleware)
        $tenant = app('current_tenant');

        if (! $tenant) {
            abort(404, 'Business not found');
        }

        $tenant = $this->repository->findBySlug($tenant->slug) ?? $tenant;

        // 2. Gatekeeper Logic (Domain Service)
        $this->visibilityService->check($tenant);

        // 3. Side-Effect Managment (Event Driven)
        // Fire and Forget. Listener handles increment logic.
        // Needs basic Event class.
        if (class_exists(\App\Events\Analytics\MicrositeViewed::class)) {
            \App\Events\Analytics\MicrositeViewed::dispatch($tenant, $request->ip(), $request->userAgent());
        }

        // 4. Transform & Respond
        $totalProductsCount = Schema::hasTable('products')
            ? $tenant->products()->where('is_visible', true)->count()
            : 0;
        $productLimit = $this->planService->getMaxProductLimit($tenant);

        $tenantData = $this->transformer->transform($tenant);
        $visibleProductsCount = count($tenantData['products']);

        $isOwner = $request->user() && $request->user()->id === $tenant->user_id;
        $crmMicrosite = $this->resolveCrmMicrositeData($tenant, $isOwner);

        return Inertia::render('Tenant/Show', [
            'tenantPlan' => $this->planService->determinePlan($tenant),
            'tenant' => $tenantData,
            'products' => $tenantData['products'],
            'hasHiddenProducts' => $totalProductsCount > $visibleProductsCount,
            'totalProductsCount' => $totalProductsCount,
            'hiddenProductsCount' => max(0, $totalProductsCount - $visibleProductsCount),
            'productLimit' => $productLimit,
            'isOwner' => $isOwner,
            'isFollowing' => false,
            'followersCount' => 0,
            'isOpen' => $this->transformer->isCurrentlyOpen($tenant),
            'openingHours' => $this->transformer->transformOpeningHours($tenant->relationLoaded('openingHours') ? $tenant->openingHours : collect()),
            'seoStructuredData' => $this->transformer->buildStructuredData($tenant),
            'canonicalUrl' => route('tenant.microsite.show', $tenant),
            'crmMicrosite' => $crmMicrosite,
        ]);
    }

    private function isDirectoryAppHost(Request $request): bool
    {
        $directoryHost = config('conecta.directory_app_host');

        if ((string) $request->route('tenant') === 'app') {
            return true;
        }

        return is_string($directoryHost)
            && $directoryHost !== ''
            && strcasecmp($request->getHost(), $directoryHost) === 0;
    }

    private function resolveCrmMicrositeData(Tenant $tenant, bool $isOwner): array
    {
        $hasCrmFeature = $tenant->hasFeature(TenantFeature::MARKETING_SUITE)
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

        if (! $enabled) {
            return $base;
        }

        if (! Schema::hasTable('crm_pipelines') || ! Schema::hasTable('crm_deals') || ! Schema::hasTable('customer_tasks')) {
            return $base;
        }

        $tenantId = (string) $tenant->getKey();

        $pipelines = CrmPipeline::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->withCount('stages')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'is_default', 'is_active', 'sort_order']);

        $deals = CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['pipeline:id,name', 'stage:id,name'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'crm_pipeline_id', 'crm_stage_id', 'title', 'status', 'value', 'currency', 'updated_at']);

        $tasks = CustomerTask::query()
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
                'pipelines' => CrmPipeline::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count(),
                'open_deals' => CrmDeal::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', CrmDeal::STATUS_OPEN)->count(),
                'won_deals' => CrmDeal::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', CrmDeal::STATUS_WON)->count(),
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

    private function passesCrmRollout(Tenant $tenant): bool
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

        $percentage = (int) config('microsite-crm.percentage', 0);
        $percentage = max(0, min(100, $percentage));

        if ($percentage <= 0) {
            return false;
        }

        if ($percentage >= 100) {
            return true;
        }

        $cohort = abs(crc32($tenantId ?: $tenantSlug)) % 100;

        return $cohort < $percentage;
    }
}
