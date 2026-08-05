<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Crm\ConvertLeadToCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\DeleteCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\ExportCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\ImportCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\ListCrmDealsRequest;
use App\Http\Requests\Api\V1\Crm\MoveCrmDealStageRequest;
use App\Http\Requests\Api\V1\Crm\StoreCrmDealRequest;
use App\Http\Requests\Api\V1\Crm\UpdateCrmDealRequest;
use App\Infrastructure\Crm\HttpCrmDealResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CrmDealController extends Controller
{
    public function __construct(private readonly HttpCrmDealResponder $responder) {}

    public function index(ListCrmDealsRequest $request): AnonymousResourceCollection
    {
        return $this->responder->index($request);
    }

    public function store(StoreCrmDealRequest $request): JsonResponse
    {
        return $this->responder->store($request);
    }

    public function import(ImportCrmDealsRequest $request): JsonResponse
    {
        return $this->responder->import($request);
    }

    public function export(ExportCrmDealsRequest $request): StreamedResponse
    {
        return $this->responder->export($request);
    }

    public function convertLead(ConvertLeadToCrmDealRequest $request, int $lead): JsonResponse
    {
        return $this->responder->convertLead($request, $lead);
    }

    public function update(UpdateCrmDealRequest $request, int $deal): mixed
    {
        return $this->responder->update($request, $deal);
    }

    public function moveStage(MoveCrmDealStageRequest $request, int $deal): mixed
    {
        return $this->responder->moveStage($request, $deal);
    }

    public function destroy(DeleteCrmDealRequest $request, int $deal): JsonResponse
    {
        return $this->responder->destroy($request, $deal);
    }
}
