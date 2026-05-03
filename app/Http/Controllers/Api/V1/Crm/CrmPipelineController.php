<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Application\TenantGovernance\Crm\CrmPipelineSummaryService;
use App\Application\TenantGovernance\Crm\CrmDefaultPipelineService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Crm\ListCrmPipelinesRequest;
use App\Http\Requests\Api\V1\Crm\ShowCrmPipelineSummaryRequest;
use App\Http\Resources\CrmPipelineResource;
use App\Models\CrmPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

}
