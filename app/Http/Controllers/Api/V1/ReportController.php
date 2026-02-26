<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\DTOs\ReportRequestData;
use App\Domain\ReportingAndExports\Jobs\GeneratePdfReportJob;
use App\Domain\ReportingAndExports\Jobs\GenerateSalesExport;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\InventoryReportRequest;
use App\Http\Requests\Api\V1\Reports\KitchenReportRequest;
use App\Http\Requests\Api\V1\Reports\SalesReportRequest;
use Illuminate\Http\JsonResponse;

/**
 * ReportController - Business Intelligence Export Engine (Hexagonal Compliant)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE:                                               ║
 * ║  - TenantContext: Decoupled tenant resolution (no $request->user())     ║
 * ║  - FormRequests: All inputs validated before reaching controller        ║
 * ║  - Async Pattern: Fire-and-forget for heavy reporting jobs             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Endpoints:
 * - POST /api/v1/reports/sales/excel → Dispatch XLSX generation
 * - POST /api/v1/reports/sales/pdf   → Dispatch PDF generation
 * - POST /api/v1/reports/inventory/pdf → Inventory manifest
 * - POST /api/v1/reports/kitchen/pdf → Kitchen prep list
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Dispatch async sales report generation (Excel).
     *
     * POST /api/v1/reports/sales/excel
     *
     * ASYNC PATTERN:
     * - Validates request via FormRequest
     * - Dispatches background job
     * - Returns HTTP 202 Accepted immediately
     * - User receives notification when report is ready
     */
    public function salesExcel(SalesReportRequest $request): JsonResponse
    {
        $tenantUuid = $this->tenantContext->getTenantUuid();

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire-and-Forget Pattern
        // ═══════════════════════════════════════════════════════════════════════
        // Job runs in background worker, freeing this HTTP request immediately.
        // User is notified via email/database when report is ready.
        GenerateSalesExport::dispatch(
            userId: $request->user()->id,
            tenantId: $tenantUuid,
            criteriaData: [
                'start_date' => $request->getStartDate(),
                'end_date' => $request->getEndDate(),
                'status' => $request->getStatus(),
            ],
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown'
        )->onQueue('exports');

        return response()->json([
            'success' => true,
            'message' => 'Su reporte se está generando en segundo plano. Le notificaremos cuando esté listo.',
            'estimated_time' => 'Aproximadamente 1-5 minutos dependiendo del volumen de datos.',
        ], 202); // HTTP 202 Accepted
    }

    /**
     * Dispatch async sales PDF generation.
     *
     * POST /api/v1/reports/sales/pdf
     *
     * ASYNC PATTERN:
     * - Validates request via FormRequest
     * - Dispatches background job
     * - Returns HTTP 202 Accepted immediately
     */
    public function salesPdf(SalesReportRequest $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire-and-Forget Pattern
        // ═══════════════════════════════════════════════════════════════════════
        $dto = ReportRequestData::fromArray([
            'tenant_id' => $tenantId,
            'report_type' => 'sales',
            'start_date' => $request->getStartDate().' 00:00:00',
            'end_date' => $request->getEndDate().' 23:59:59',
        ]);

        GeneratePdfReportJob::dispatch(
            data: $dto,
            requestorId: $request->user()->id
        )->onQueue('reports');

        return response()->json([
            'success' => true,
            'message' => 'Su reporte PDF se está generando. Recibirá una notificación cuando esté listo.',
            'report_type' => 'sales',
            'estimated_time' => 'Aproximadamente 1-3 minutos.',
        ], 202);
    }

    /**
     * Dispatch async inventory PDF generation.
     *
     * POST /api/v1/reports/inventory/pdf
     *
     * ASYNC PATTERN: Non-blocking response.
     */
    public function inventoryPdf(InventoryReportRequest $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire-and-Forget Pattern
        // ═══════════════════════════════════════════════════════════════════════
        $dto = ReportRequestData::fromArray([
            'tenant_id' => $tenantId,
            'report_type' => 'inventory',
            'start_date' => now()->toDateTimeString(), // Unused by inventory, but required by DTO
            'end_date' => now()->toDateTimeString(),
        ]);

        GeneratePdfReportJob::dispatch(
            data: $dto,
            requestorId: $request->user()->id
        )->onQueue('reports');

        return response()->json([
            'success' => true,
            'message' => 'Su reporte de inventario se está generando. Recibirá una notificación cuando esté listo.',
            'report_type' => 'inventory',
            'estimated_time' => 'Aproximadamente 1-2 minutos.',
        ], 202);
    }

    /**
     * Dispatch async kitchen prep list PDF generation.
     *
     * POST /api/v1/reports/kitchen/pdf
     *
     * ASYNC PATTERN: Non-blocking response.
     */
    public function kitchenPdf(KitchenReportRequest $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire-and-Forget Pattern
        // ═══════════════════════════════════════════════════════════════════════
        $dto = ReportRequestData::fromArray([
            'tenant_id' => $tenantId,
            'report_type' => 'kitchen',
            'start_date' => now()->toDateTimeString(),
            'end_date' => now()->toDateTimeString(),
        ]);

        GeneratePdfReportJob::dispatch(
            data: $dto,
            requestorId: $request->user()->id
        )->onQueue('reports');

        return response()->json([
            'success' => true,
            'message' => 'Su lista de preparación se está generando. Recibirá una notificación cuando esté listo.',
            'report_type' => 'kitchen',
            'estimated_time' => 'Aproximadamente 30 segundos.',
        ], 202);
    }
}
