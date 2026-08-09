<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Domain\Crm\Enums\DealStatus;
use App\Http\Resources\Crm\DealResource;
use App\Models\CrmDeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * DealController - REST CRUD para deals + acciones (moveStage, markWon, markLost).
 */
class DealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmDeal::class);

        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-Id');
        $query = CrmDeal::query()->where('tenant_id', $tenantId);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($pipelineId = $request->query('pipeline_id')) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($stageId = $request->query('stage_id')) {
            $query->where('stage_id', $stageId);
        }
        if ($contactId = $request->query('contact_id')) {
            $query->where('contact_id', $contactId);
        }

        $perPage = (int) $request->query('per_page', 25);

        return DealResource::collection($query->paginate(min($perPage, 100)))->response();
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', CrmDeal::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'pipeline_id' => ['required', 'string', 'exists:crm_pipelines,id'],
            'stage_id' => ['required', 'string', 'exists:crm_stages,id'],
            'contact_id' => ['nullable', 'string', 'exists:crm_contacts,id'],
            'company_id' => ['nullable', 'string', 'exists:crm_companies,id'],
            'owner_id' => ['nullable', 'string', 'exists:users,id'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $deal = CrmDeal::create($data);

        return (new DealResource($deal))->response()->setStatusCode(201);
    }

    public function show(CrmDeal $deal): JsonResponse
    {
        Gate::authorize('view', $deal);

        return (new DealResource($deal->load(['contact', 'company', 'owner', 'pipeline', 'stage'])))->response();
    }

    public function update(Request $request, CrmDeal $deal): JsonResponse
    {
        Gate::authorize('update', $deal);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'stage_id' => ['sometimes', 'string', 'exists:crm_stages,id'],
            'contact_id' => ['sometimes', 'nullable', 'string', 'exists:crm_contacts,id'],
            'company_id' => ['sometimes', 'nullable', 'string', 'exists:crm_companies,id'],
            'owner_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);
        $deal->update($data);

        return (new DealResource($deal))->response();
    }

    public function destroy(CrmDeal $deal): JsonResponse
    {
        Gate::authorize('delete', $deal);
        $deal->delete();

        return response()->json(['message' => 'Deal deleted successfully']);
    }

    public function moveStage(Request $request, CrmDeal $deal): JsonResponse
    {
        Gate::authorize('update', $deal);
        $data = $request->validate([
            'stage_id' => ['required', 'string', 'exists:crm_stages,id'],
        ]);
        $deal->update(['stage_id' => $data['stage_id']]);

        return (new DealResource($deal))->response();
    }

    public function markWon(CrmDeal $deal): JsonResponse
    {
        Gate::authorize('update', $deal);
        $deal->update([
            'status' => DealStatus::Won->value,
            'won_at' => now(),
            'probability' => 100,
        ]);

        return (new DealResource($deal))->response();
    }

    public function markLost(Request $request, CrmDeal $deal): JsonResponse
    {
        Gate::authorize('update', $deal);
        $data = $request->validate([
            'lost_reason' => ['required', 'string', 'max:100'],
        ]);
        $deal->update([
            'status' => DealStatus::Lost->value,
            'lost_at' => now(),
            'lost_reason' => $data['lost_reason'],
            'probability' => 0,
        ]);

        return (new DealResource($deal))->response();
    }
}
