<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Application\TenantGovernance\Crm\CrmDefaultPipelineService;
use App\Application\TenantGovernance\Crm\CrmPipelineSummaryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Crm\ListCrmPipelinesRequest;
use App\Http\Requests\Api\V1\Crm\ShowCrmPipelineSummaryRequest;
use App\Http\Requests\Api\V1\Crm\StoreCrmPipelineRequest;
use App\Http\Requests\Api\V1\Crm\StoreCrmStageRequest;
use App\Http\Requests\Api\V1\Crm\UpdateCrmPipelineRequest;
use App\Http\Requests\Api\V1\Crm\UpdateCrmStageRequest;
use App\Infrastructure\Crm\HttpCrmPipelineResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CrmPipelineController extends Controller
{
    public function __construct(private readonly HttpCrmPipelineResponder $responder) {}

    public function index(ListCrmPipelinesRequest $request, CrmDefaultPipelineService $defaults): AnonymousResourceCollection
    {
        return $this->responder->index($request, $defaults);
    }

    public function summary(ShowCrmPipelineSummaryRequest $request, CrmPipelineSummaryService $summary, CrmDefaultPipelineService $defaults): JsonResponse
    {
        return $this->responder->summary($request, $summary, $defaults);
    }

    public function store(StoreCrmPipelineRequest $request): JsonResponse
    {
        return $this->responder->store($request);
    }

    public function update(UpdateCrmPipelineRequest $request, int $pipeline): mixed
    {
        return $this->responder->update($request, $pipeline);
    }

    public function destroy(UpdateCrmPipelineRequest $request, int $pipeline): JsonResponse
    {
        return $this->responder->destroy($request, $pipeline);
    }

    public function storeStage(StoreCrmStageRequest $request, int $pipeline): JsonResponse
    {
        return $this->responder->storeStage($request, $pipeline);
    }

    public function updateStage(UpdateCrmStageRequest $request, int $stage): mixed
    {
        return $this->responder->updateStage($request, $stage);
    }

    public function destroyStage(UpdateCrmStageRequest $request, int $stage): JsonResponse
    {
        return $this->responder->destroyStage($request, $stage);
    }
}
