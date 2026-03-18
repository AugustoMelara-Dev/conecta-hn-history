<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Shared\DTOs\ReportRequestData;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\InventoryReportRequest;
use App\Http\Requests\Api\V1\Reports\KitchenReportRequest;
use App\Http\Requests\Api\V1\Reports\SalesReportRequest;
use App\Models\User;
use App\Infrastructure\Jobs\Exports\GenerateSalesExport;
use App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob;
use Illuminate\Http\JsonResponse;

final class ReportController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function salesExcel(SalesReportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $this->tenantContext->getTenantUuid();

        GenerateSalesExport::dispatch(
            userId: $user->getAuthIdentifier(),
            tenantId: $tenantId,
            criteriaData: [
                'start_date' => $request->getStartDate(),
                'end_date' => $request->getEndDate(),
                'status' => $request->getStatus(),
            ],
            requestIp: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown'
        )->onQueue('exports');

        return response()->json([
            'success' => true,
            'message' => 'Su reporte se esta generando en segundo plano. Le notificaremos cuando este listo.',
            'estimated_time' => 'Aproximadamente 1-5 minutos dependiendo del volumen de datos.',
        ], 202);
    }

    public function salesPdf(SalesReportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        GeneratePdfReportJob::dispatch(
            data: $this->buildReportRequestData(
                tenantId: $this->tenantContext->getTenantUuid(),
                reportType: 'sales',
                startDate: $request->getStartDate(),
                endDate: $request->getEndDate(),
            ),
            requestorId: $user->getAuthIdentifier()
        )->onQueue('reports');

        return response()->json([
            'success' => true,
            'message' => 'Su reporte PDF se esta generando. Recibira una notificacion cuando este listo.',
            'report_type' => 'sales',
            'estimated_time' => 'Aproximadamente 1-3 minutos.',
        ], 202);
    }

    public function inventoryPdf(InventoryReportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        GeneratePdfReportJob::dispatch(
            data: $this->buildReportRequestData(
                tenantId: $this->tenantContext->getTenantUuid(),
                reportType: 'inventory',
            ),
            requestorId: $user->getAuthIdentifier()
        )->onQueue('reports');

        return response()->json([
            'success' => true,
            'message' => 'Su reporte de inventario se esta generando. Recibira una notificacion cuando este listo.',
            'report_type' => 'inventory',
            'estimated_time' => 'Aproximadamente 1-2 minutos.',
        ], 202);
    }

    public function kitchenPdf(KitchenReportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        GeneratePdfReportJob::dispatch(
            data: $this->buildReportRequestData(
                tenantId: $this->tenantContext->getTenantUuid(),
                reportType: 'kitchen',
            ),
            requestorId: $user->getAuthIdentifier()
        )->onQueue('reports');

        return response()->json([
            'success' => true,
            'message' => 'Su lista de preparacion se esta generando. Recibira una notificacion cuando este listo.',
            'report_type' => 'kitchen',
            'estimated_time' => 'Aproximadamente 30 segundos.',
        ], 202);
    }

    private function buildReportRequestData(
        string $tenantId,
        string $reportType,
        ?string $startDate = null,
        ?string $endDate = null
    ): ReportRequestData {
        $resolvedStartDate = $startDate ?? now()->toDateString();
        $resolvedEndDate = $endDate ?? now()->toDateString();

        return new ReportRequestData(
            tenantId: $tenantId,
            reportType: $reportType,
            startDate: new \DateTimeImmutable($resolvedStartDate.' 00:00:00'),
            endDate: new \DateTimeImmutable($resolvedEndDate.' 23:59:59'),
        );
    }
}
