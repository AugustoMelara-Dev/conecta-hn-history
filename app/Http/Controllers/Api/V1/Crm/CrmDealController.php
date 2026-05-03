<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
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
}
