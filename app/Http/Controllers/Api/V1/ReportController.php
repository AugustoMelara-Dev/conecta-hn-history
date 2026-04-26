<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Reporting\Commands\QueuePdfReportCommand;
use App\Application\Reporting\Commands\QueueSalesExportCommand;
use App\Application\Reporting\Handlers\QueuePdfReportHandler;
use App\Application\Reporting\Handlers\QueueSalesExportHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\InventoryReportRequest;
use App\Http\Requests\Api\V1\Reports\KitchenReportRequest;
use App\Http\Requests\Api\V1\Reports\SalesReportRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class ReportController extends Controller
{
    public function salesExcel(
        SalesReportRequest $request,
        QueueSalesExportHandler $handler
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $handler->handle(new QueueSalesExportCommand(
            requestorId: $user->getAuthIdentifier(),
            tenantId: (string) $request->tenantForRequest()->getKey(),
            criteriaData: [
                'start_date' => $request->getStartDate(),
                'end_date' => $request->getEndDate(),
                'status' => $request->getStatus(),
            ],
            requestIp: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown',
        ));

        return response()->json([
            'success' => true,
            'message' => 'Su reporte se esta generando en segundo plano. Le notificaremos cuando este listo.',
            'estimated_time' => 'Aproximadamente 1-5 minutos dependiendo del volumen de datos.',
        ], 202);
    }

    public function salesPdf(
        SalesReportRequest $request,
        QueuePdfReportHandler $handler
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $handler->handle(new QueuePdfReportCommand(
            tenantId: (string) $request->tenantForRequest()->getKey(),
            requestorId: $user->getAuthIdentifier(),
            reportType: 'sales',
            startDate: $request->getStartDate(),
            endDate: $request->getEndDate(),
        ));

        return response()->json([
            'success' => true,
            'message' => 'Su reporte PDF se esta generando. Recibira una notificacion cuando este listo.',
            'report_type' => 'sales',
            'estimated_time' => 'Aproximadamente 1-3 minutos.',
        ], 202);
    }

    public function inventoryPdf(
        InventoryReportRequest $request,
        QueuePdfReportHandler $handler
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $handler->handle(new QueuePdfReportCommand(
            tenantId: (string) $request->tenantForRequest()->getKey(),
            requestorId: $user->getAuthIdentifier(),
            reportType: 'inventory',
        ));

        return response()->json([
            'success' => true,
            'message' => 'Su reporte de inventario se esta generando. Recibira una notificacion cuando este listo.',
            'report_type' => 'inventory',
            'estimated_time' => 'Aproximadamente 1-2 minutos.',
        ], 202);
    }

    public function kitchenPdf(
        KitchenReportRequest $request,
        QueuePdfReportHandler $handler
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $handler->handle(new QueuePdfReportCommand(
            tenantId: (string) $request->tenantForRequest()->getKey(),
            requestorId: $user->getAuthIdentifier(),
            reportType: 'kitchen',
        ));

        return response()->json([
            'success' => true,
            'message' => 'Su lista de preparacion se esta generando. Recibira una notificacion cuando este listo.',
            'report_type' => 'kitchen',
            'estimated_time' => 'Aproximadamente 30 segundos.',
        ], 202);
    }
}
