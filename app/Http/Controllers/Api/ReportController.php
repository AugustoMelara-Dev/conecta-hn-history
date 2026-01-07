<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReportController - Business Intelligence Export Engine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STREAMING: Memory-safe exports for 100k+ records                       ║
 * ║  FORMATS: Excel (accounting) and PDF (operational manifests)            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Endpoints:
 * - GET /api/reports/sales/excel   → Download XLSX
 * - GET /api/reports/sales/pdf     → Download PDF
 * - GET /api/reports/inventory/pdf → Inventory check manifest
 */
class ReportController extends Controller
{
    /**
     * Dispatch async sales report generation.
     *
     * POST /api/reports/sales/excel
     *
     * ASYNC PATTERN:
     * - Validates request
     * - Dispatches background job
     * - Returns HTTP 202 Accepted immediately
     * - User receives notification when report is ready
     */
    public function salesExcel(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            abort(403, 'No tiene un negocio asociado.');
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string|in:pending,confirmed,processing,ready,delivered,cancelled',
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire-and-Forget Pattern
        // ═══════════════════════════════════════════════════════════════════════
        // Job runs in background worker, freeing this HTTP request immediately.
        // User is notified via email/database when report is ready.
        \App\Domain\ReportingAndExports\Jobs\GenerateSalesExport::dispatch(
            userId: $request->user()->id,
            tenantId: $tenant->id,
            criteriaData: $validated,
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
     * POST /api/reports/sales/pdf
     *
     * ASYNC PATTERN:
     * - Validates request
     * - Dispatches background job
     * - Returns HTTP 202 Accepted immediately
     */
    public function salesPdf(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            abort(403, 'No tiene un negocio asociado.');
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string|in:pending,confirmed,processing,ready,delivered,cancelled',
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire-and-Forget Pattern
        // ═══════════════════════════════════════════════════════════════════════
        // Create DTO from validated request
        // Note: For 'sales' report, start/end date are optional in validation, 
        // but DTO requires them. We map nulls to sensible defaults or handle validation earlier.
        // Assuming validation ensures dates if strictness is required.
        // For DTO strictness, we'll default to today/now if null, or handle business rule.
        // IMPORTANT: The DTO expects DateTimeImmutable, validation sends strings.
        
        $dto = \App\Application\DTOs\ReportRequestData::fromArray([
            'tenant_id' => $tenant->id,
            'report_type' => 'sales',
            'start_date' => $validated['start_date'] ?? now()->subMonth()->toDateTimeString(),
            'end_date' => $validated['end_date'] ?? now()->toDateTimeString(),
        ]);

        \App\Domain\ReportingAndExports\Jobs\GeneratePdfReportJob::dispatch(
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
     * POST /api/reports/inventory/pdf
     *
     * ASYNC PATTERN: Non-blocking response.
     */
    public function inventoryPdf(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            abort(403, 'No tiene un negocio asociado.');
        }

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire-and-Forget Pattern
        // ═══════════════════════════════════════════════════════════════════════
        $dto = \App\Application\DTOs\ReportRequestData::fromArray([
            'tenant_id' => $tenant->id,
            'report_type' => 'inventory',
            'start_date' => now()->toDateTimeString(), // Unused by inventory, but required by DTO contract
            'end_date' => now()->toDateTimeString(),   // Unused by inventory
        ]);

        \App\Domain\ReportingAndExports\Jobs\GeneratePdfReportJob::dispatch(
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
     * POST /api/reports/kitchen/pdf
     *
     * ASYNC PATTERN: Non-blocking response.
     */
    public function kitchenPdf(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            abort(403, 'No tiene un negocio asociado.');
        }

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC DISPATCH: Fire-and-Forget Pattern
        // ═══════════════════════════════════════════════════════════════════════
        $dto = \App\Application\DTOs\ReportRequestData::fromArray([
            'tenant_id' => $tenant->id,
            'report_type' => 'kitchen',
            'start_date' => now()->toDateTimeString(),
            'end_date' => now()->toDateTimeString(),
        ]);

        \App\Domain\ReportingAndExports\Jobs\GeneratePdfReportJob::dispatch(
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
