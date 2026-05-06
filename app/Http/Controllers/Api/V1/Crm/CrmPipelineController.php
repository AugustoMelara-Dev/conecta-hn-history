<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Application\TenantGovernance\Crm\CrmPipelineSummaryService;
use App\Application\TenantGovernance\Crm\CrmDefaultPipelineService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Crm\ListCrmPipelinesRequest;
use App\Http\Requests\Api\V1\Crm\ShowCrmPipelineSummaryRequest;
use App\Http\Requests\Api\V1\Crm\StoreCrmPipelineRequest;
use App\Http\Requests\Api\V1\Crm\StoreCrmStageRequest;
use App\Http\Requests\Api\V1\Crm\UpdateCrmPipelineRequest;
use App\Http\Requests\Api\V1\Crm\UpdateCrmStageRequest;
use App\Http\Resources\CrmPipelineResource;
use App\Http\Resources\CrmStageResource;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

final class CrmPipelineController extends Controller
{
    public function index(ListCrmPipelinesRequest $request, CrmDefaultPipelineService $defaults): AnonymousResourceCollection
    {
        $tenant = $request->tenantForRequest();
        $defaults->ensureForTenant($tenant);

        return CrmPipelineResource::collection(
            CrmPipeline::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('is_active', true)
                ->with('stages')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function summary(ShowCrmPipelineSummaryRequest $request, CrmPipelineSummaryService $summary, CrmDefaultPipelineService $defaults): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $defaults->ensureForTenant($tenant);

        return response()->json([
            'data' => $summary->summarize($tenant),
        ]);
    }

    public function store(StoreCrmPipelineRequest $request): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $data = $this->pipelinePayload($tenant->getKey(), $request->validated());

        $pipeline = CrmPipeline::query()
            ->withoutGlobalScopes()
            ->create($data);

        $this->normalizeDefaultPipeline($pipeline);

        return (new CrmPipelineResource($pipeline->refresh()->load('stages')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCrmPipelineRequest $request, int $pipeline): CrmPipelineResource
    {
        $tenant = $request->tenantForRequest();
        $crmPipeline = $this->pipelineForTenant($tenant->getKey(), $pipeline);

        $crmPipeline->fill($this->pipelinePayload($tenant->getKey(), $request->validated(), $crmPipeline))->save();
        $this->normalizeDefaultPipeline($crmPipeline);

        return new CrmPipelineResource($crmPipeline->refresh()->load('stages'));
    }

    public function destroy(UpdateCrmPipelineRequest $request, int $pipeline): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $crmPipeline = $this->pipelineForTenant($tenant->getKey(), $pipeline);

        abort_if((bool) $crmPipeline->is_default, 422, 'No puedes eliminar el pipeline principal.');
        abort_if($crmPipeline->deals()->exists(), 422, 'No puedes eliminar un pipeline con oportunidades.');

        $crmPipeline->delete();

        return response()->json(['message' => 'Pipeline eliminado.']);
    }

    public function storeStage(StoreCrmStageRequest $request, int $pipeline): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $crmPipeline = $this->pipelineForTenant($tenant->getKey(), $pipeline);

        $stage = CrmStage::query()
            ->withoutGlobalScopes()
            ->create($this->stagePayload($tenant->getKey(), $crmPipeline->getKey(), $request->validated()));

        $this->normalizeDefaultStage($stage);

        return (new CrmStageResource($stage->refresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function updateStage(UpdateCrmStageRequest $request, int $stage): CrmStageResource
    {
        $tenant = $request->tenantForRequest();
        $crmStage = $this->stageForTenant($tenant->getKey(), $stage);

        $crmStage->fill($this->stagePayload($tenant->getKey(), $crmStage->crm_pipeline_id, $request->validated(), $crmStage))->save();
        $this->normalizeDefaultStage($crmStage);

        return new CrmStageResource($crmStage->refresh());
    }

    public function destroyStage(UpdateCrmStageRequest $request, int $stage): JsonResponse
    {
        $tenant = $request->tenantForRequest();
        $crmStage = $this->stageForTenant($tenant->getKey(), $stage);

        abort_if((bool) $crmStage->is_default, 422, 'No puedes eliminar la etapa principal.');
        abort_if($crmStage->deals()->exists(), 422, 'No puedes eliminar una etapa con oportunidades.');

        $crmStage->delete();

        return response()->json(['message' => 'Etapa eliminada.']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pipelinePayload(mixed $tenantId, array $data, ?CrmPipeline $pipeline = null): array
    {
        $key = (string) ($data['key'] ?? $pipeline?->key ?? Str::slug((string) $data['name']));
        $key = Str::slug($key);

        abort_if($key === '', 422, 'La llave del pipeline no es valida.');
        $this->assertUniquePipelineKey((string) $tenantId, $key, $pipeline);

        return [
            'tenant_id' => (string) $tenantId,
            'name' => (string) ($data['name'] ?? $pipeline?->name),
            'key' => $key,
            'description' => array_key_exists('description', $data) ? $data['description'] : $pipeline?->description,
            'is_default' => (bool) ($data['is_default'] ?? $pipeline?->is_default ?? false),
            'is_active' => (bool) ($data['is_active'] ?? $pipeline?->is_active ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? $pipeline?->sort_order ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function stagePayload(mixed $tenantId, int $pipelineId, array $data, ?CrmStage $stage = null): array
    {
        $key = (string) ($data['key'] ?? $stage?->key ?? Str::slug((string) $data['name']));
        $key = Str::slug($key);

        abort_if($key === '', 422, 'La llave de la etapa no es valida.');
        $this->assertUniqueStageKey($pipelineId, $key, $stage);

        return [
            'tenant_id' => (string) $tenantId,
            'crm_pipeline_id' => $pipelineId,
            'name' => (string) ($data['name'] ?? $stage?->name),
            'key' => $key,
            'type' => (string) ($data['type'] ?? $stage?->type ?? CrmStage::TYPE_OPEN),
            'probability' => (int) ($data['probability'] ?? $stage?->probability ?? 0),
            'is_default' => (bool) ($data['is_default'] ?? $stage?->is_default ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? $stage?->sort_order ?? 0),
        ];
    }

    private function pipelineForTenant(mixed $tenantId, int $pipelineId): CrmPipeline
    {
        return CrmPipeline::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantId)
            ->findOrFail($pipelineId);
    }

    private function stageForTenant(mixed $tenantId, int $stageId): CrmStage
    {
        return CrmStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantId)
            ->findOrFail($stageId);
    }

    private function assertUniquePipelineKey(string $tenantId, string $key, ?CrmPipeline $pipeline): void
    {
        $exists = CrmPipeline::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->when($pipeline instanceof CrmPipeline, fn ($query) => $query->whereKeyNot($pipeline->getKey()))
            ->exists();

        abort_if($exists, 422, 'Ya existe un pipeline con esa llave.');
    }

    private function assertUniqueStageKey(int $pipelineId, string $key, ?CrmStage $stage): void
    {
        $exists = CrmStage::query()
            ->withoutGlobalScopes()
            ->where('crm_pipeline_id', $pipelineId)
            ->where('key', $key)
            ->when($stage instanceof CrmStage, fn ($query) => $query->whereKeyNot($stage->getKey()))
            ->exists();

        abort_if($exists, 422, 'Ya existe una etapa con esa llave.');
    }

    private function normalizeDefaultPipeline(CrmPipeline $pipeline): void
    {
        if (! (bool) $pipeline->is_default) {
            return;
        }

        CrmPipeline::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $pipeline->tenant_id)
            ->whereKeyNot($pipeline->getKey())
            ->update(['is_default' => false]);
    }

    private function normalizeDefaultStage(CrmStage $stage): void
    {
        if (! (bool) $stage->is_default) {
            return;
        }

        CrmStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $stage->tenant_id)
            ->where('crm_pipeline_id', $stage->crm_pipeline_id)
            ->whereKeyNot($stage->getKey())
            ->update(['is_default' => false]);
    }
}
