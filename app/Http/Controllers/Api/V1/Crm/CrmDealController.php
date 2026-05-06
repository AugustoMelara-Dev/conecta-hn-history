<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Application\TenantGovernance\Crm\CrmDefaultPipelineService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Crm\ConvertLeadToCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\ExportCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\ImportCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\ListCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\MoveCrmDealStageRequest;
use App\Http\Requests\Api\V1\Crm\StoreCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\UpdateCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\DeleteCrmDealRequest;
use App\Http\Resources\CrmDealResource;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CrmDealController extends Controller
{
    public function index(ListCrmDealsRequest $request): AnonymousResourceCollection
    {
        $tenant = $request->tenantForRequest();
        $validated = $request->validated();

        $query = CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->with(['customerProfile', 'lead', 'stage'])
            ->latest('updated_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['stage_id'])) {
            $query->where('crm_stage_id', (int) $validated['stage_id']);
        }

        if (isset($validated['owner_user_id'])) {
            $query->where('owner_user_id', (string) $validated['owner_user_id']);
        }

        return CrmDealResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function store(StoreCrmDealRequest $request): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $data = $this->validatedPayload($tenant, $request->validated());

        $deal = CrmDeal::query()->withoutGlobalScopes()->create($data);

        return (new CrmDealResource($deal->load(['customerProfile', 'lead', 'stage'])))
            ->response()
            ->setStatusCode(201);
    }

    public function import(ImportCrmDealsRequest $request): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $created = collect($request->validated('deals'))
            ->map(function (array $row) use ($tenant): CrmDeal {
                $defaults = $this->defaultPipelineAndStage($tenant);

                return CrmDeal::query()
                    ->withoutGlobalScopes()
                    ->create($this->validatedPayload($tenant, [
                        'pipeline_id' => $row['pipeline_id'] ?? $defaults['pipeline']->getKey(),
                        'stage_id' => $row['stage_id'] ?? $defaults['stage']->getKey(),
                        'title' => $row['title'],
                        'value' => $row['value'] ?? 0,
                        'currency' => $row['currency'] ?? 'HNL',
                        'status' => $row['status'] ?? null,
                        'source' => $row['source'] ?? 'crm_import',
                        'expected_close_at' => $row['expected_close_at'] ?? null,
                        'closed_at' => $row['closed_at'] ?? null,
                        'lost_reason' => $row['lost_reason'] ?? null,
                        'notes' => $row['notes'] ?? null,
                        'customer_profile_id' => $row['customer_profile_id'] ?? null,
                        'lead_id' => $row['lead_id'] ?? null,
                        'owner_user_id' => $row['owner_user_id'] ?? null,
                        'location_id' => $row['location_id'] ?? null,
                    ]));
            });

        $created->each(fn (CrmDeal $deal): CrmDeal => $deal->load(['customerProfile', 'lead', 'stage']));

        return response()->json([
            'message' => 'Oportunidades importadas.',
            'created_count' => $created->count(),
            'data' => CrmDealResource::collection($created),
        ], 201);
    }

    public function export(ExportCrmDealsRequest $request): StreamedResponse
    {
        $tenant = $request->tenantForRequest();
        $validated = $request->validated();
        $filename = 'crm-deals-tenant-'.$tenant->getKey().'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($tenant, $validated): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'title',
                'status',
                'pipeline',
                'stage',
                'value',
                'currency',
                'expected_close_at',
                'closed_at',
                'source',
                'customer',
                'lead',
            ]);

            $this->exportQuery($tenant, $validated)
                ->chunkById(100, function ($deals) use ($handle): void {
                    foreach ($deals as $deal) {
                        fputcsv($handle, [
                            $deal->getKey(),
                            $this->safeCsv((string) $deal->title),
                            $deal->status,
                            $this->safeCsv((string) ($deal->pipeline?->name ?? '')),
                            $this->safeCsv((string) ($deal->stage?->name ?? '')),
                            $deal->value,
                            $deal->currency,
                            $deal->expected_close_at?->toDateString(),
                            $deal->closed_at?->toIso8601String(),
                            $this->safeCsv((string) ($deal->source ?? '')),
                            $this->safeCsv((string) ($deal->customerProfile?->name ?? '')),
                            $this->safeCsv((string) ($deal->lead?->name ?? '')),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function convertLead(ConvertLeadToCrmDealRequest $request, int $lead, CrmDefaultPipelineService $defaults): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $sourceLead = Lead::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->findOrFail($lead);

        $existingDeal = CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('lead_id', $sourceLead->getKey())
            ->first();

        if ($existingDeal instanceof CrmDeal) {
            return (new CrmDealResource($existingDeal->load(['customerProfile', 'lead', 'stage'])))
                ->response()
                ->setStatusCode(200);
        }

        $pipeline = $request->validated('pipeline_id')
            ? CrmPipeline::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where('is_active', true)
                ->findOrFail((int) $request->validated('pipeline_id'))
            : $defaults->ensureForTenant($tenant);

        $stage = $request->validated('stage_id')
            ? CrmStage::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where('crm_pipeline_id', $pipeline->getKey())
                ->findOrFail((int) $request->validated('stage_id'))
            : $pipeline->stages()->where('is_default', true)->first()
                ?? $pipeline->stages()->orderBy('sort_order')->firstOrFail();

        $customerProfile = $this->customerProfileForLead($tenant, $sourceLead);
        $data = $this->validatedPayload($tenant, array_filter([
            'pipeline_id' => $pipeline->getKey(),
            'stage_id' => $stage->getKey(),
            'customer_profile_id' => $customerProfile?->getKey(),
            'lead_id' => $sourceLead->getKey(),
            'title' => $request->validated('title') ?: "Oportunidad: {$sourceLead->name}",
            'value' => $request->validated('value') ?? 0,
            'currency' => $request->validated('currency') ?? 'HNL',
            'owner_user_id' => $request->validated('owner_user_id'),
            'expected_close_at' => $request->validated('expected_close_at'),
            'source' => $sourceLead->source ?: 'lead_conversion',
            'notes' => $request->validated('notes') ?: $sourceLead->message,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        $deal = CrmDeal::query()->withoutGlobalScopes()->create($data);
        $sourceLead->forceFill([
            'customer_profile_id' => $customerProfile?->getKey(),
            'status' => 'converted',
        ])->save();

        return (new CrmDealResource($deal->load(['customerProfile', 'lead', 'stage'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCrmDealRequest $request, int $deal): CrmDealResource
    {
        $tenant = $request->tenantForRequest();
        $crmDeal = $this->dealForTenant($tenant, $deal);
        $this->assertTenantRecord($tenant, $crmDeal);

        $data = $this->validatedPayload($tenant, $request->validated(), $crmDeal);
        $crmDeal->fill($data)->save();

        return new CrmDealResource($crmDeal->refresh()->load(['customerProfile', 'lead', 'stage']));
    }

    public function moveStage(MoveCrmDealStageRequest $request, int $deal): CrmDealResource
    {
        $tenant = $request->tenantForRequest();
        $crmDeal = $this->dealForTenant($tenant, $deal);
        $this->assertTenantRecord($tenant, $crmDeal);

        $stage = CrmStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('crm_pipeline_id', $crmDeal->crm_pipeline_id)
            ->findOrFail((int) $request->validated('stage_id'));

        $crmDeal->forceFill([
            'crm_stage_id' => $stage->getKey(),
            'status' => $this->statusForStage($stage),
            'closed_at' => $stage->type === CrmStage::TYPE_OPEN ? null : ($crmDeal->closed_at ?? now()),
        ])->save();

        return new CrmDealResource($crmDeal->refresh()->load(['customerProfile', 'lead', 'stage']));
    }

    public function destroy(DeleteCrmDealRequest $request, int $deal): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $crmDeal = $this->dealForTenant($tenant, $deal);
        $this->assertTenantRecord($tenant, $crmDeal);

        $crmDeal->delete();

        return response()->json([
            'message' => 'Deal eliminado.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedPayload(Tenant $tenant, array $data, ?CrmDeal $deal = null): array
    {
        $pipelineId = (int) ($data['pipeline_id'] ?? $deal?->crm_pipeline_id);
        $stageId = (int) ($data['stage_id'] ?? $deal?->crm_stage_id);

        $pipeline = CrmPipeline::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->findOrFail($pipelineId);

        $stage = CrmStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('crm_pipeline_id', $pipeline->getKey())
            ->findOrFail($stageId);

        $payload = [
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'status' => (string) ($data['status'] ?? $deal?->status ?? $this->statusForStage($stage)),
            'currency' => strtoupper((string) ($data['currency'] ?? $deal?->currency ?? 'HNL')),
        ];

        foreach (['title', 'value', 'source', 'expected_close_at', 'closed_at', 'lost_reason', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        foreach ([
            'customer_profile_id' => CustomerProfile::class,
            'lead_id' => Lead::class,
            'owner_user_id' => User::class,
            'location_id' => Location::class,
        ] as $field => $modelClass) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $this->nullableTenantRecordId($tenant, $modelClass, $data[$field]);
            }
        }

        if (($payload['status'] ?? null) === CrmDeal::STATUS_OPEN) {
            $payload['closed_at'] = null;
        } elseif (! array_key_exists('closed_at', $payload)) {
            $payload['closed_at'] = $deal?->closed_at ?? now();
        }

        return $payload;
    }

    private function assertTenantRecord(Tenant $tenant, CrmDeal $deal): void
    {
        abort_unless((string) $deal->tenant_id === (string) $tenant->getKey(), 404);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function nullableTenantRecordId(Tenant $tenant, string $modelClass, mixed $id): mixed
    {
        if ($id === null || $id === '') {
            return null;
        }

        $record = $modelClass::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->findOrFail($id);

        return $record->getKey();
    }

    private function statusForStage(CrmStage $stage): string
    {
        return match ($stage->type) {
            CrmStage::TYPE_WON => CrmDeal::STATUS_WON,
            CrmStage::TYPE_LOST => CrmDeal::STATUS_LOST,
            default => CrmDeal::STATUS_OPEN,
        };
    }

    private function dealForTenant(Tenant $tenant, int $dealId): CrmDeal
    {
        return CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->findOrFail($dealId);
    }

    /**
     * @return array{pipeline: CrmPipeline, stage: CrmStage}
     */
    private function defaultPipelineAndStage(Tenant $tenant): array
    {
        $pipeline = app(CrmDefaultPipelineService::class)->ensureForTenant($tenant);
        $stage = $pipeline->stages()->where('is_default', true)->first()
            ?? $pipeline->stages()->orderBy('sort_order')->firstOrFail();

        return [
            'pipeline' => $pipeline,
            'stage' => $stage,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function exportQuery(Tenant $tenant, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->with(['pipeline', 'stage', 'customerProfile', 'lead'])
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['pipeline_id']), fn ($query) => $query->where('crm_pipeline_id', (int) $filters['pipeline_id']))
            ->when(isset($filters['stage_id']), fn ($query) => $query->where('crm_stage_id', (int) $filters['stage_id']))
            ->orderBy('id');
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }

    private function customerProfileForLead(Tenant $tenant, Lead $lead): ?CustomerProfile
    {
        if ($lead->customer_profile_id !== null) {
            return CustomerProfile::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $tenant->getKey())
                ->find($lead->customer_profile_id);
        }

        $query = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey());

        $existing = null;
        if (filled($lead->email)) {
            $existing = (clone $query)->where('normalized_email', strtolower((string) $lead->email))->first();
        }

        if (! $existing instanceof CustomerProfile && filled($lead->phone)) {
            $existing = (clone $query)->where('normalized_phone', preg_replace('/\D+/', '', (string) $lead->phone))->first();
        }

        if ($existing instanceof CustomerProfile) {
            return $existing;
        }

        if (blank($lead->name) && blank($lead->email) && blank($lead->phone)) {
            return null;
        }

        return CustomerProfile::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $tenant->getKey(),
                'name' => (string) ($lead->name ?: 'Lead sin nombre'),
                'email' => $lead->email,
                'phone' => $lead->phone,
                'preferred_channel' => filled($lead->phone) ? 'whatsapp' : 'email',
                'lifecycle_stage' => CustomerProfile::LIFECYCLE_PROSPECT,
                'customer_segment' => CustomerProfile::SEGMENT_PROSPECT,
                'acquisition_channel' => $lead->source,
                'last_lead_at' => $lead->created_at,
            ]);
    }
}
