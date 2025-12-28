<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\SalesExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        \App\Jobs\GenerateSalesReportJob::dispatch(
            tenant: $tenant,
            filters: $validated,
            requestor: $request->user()
        )->onQueue('reports');

        return response()->json([
            'success' => true,
            'message' => 'Su reporte se está generando en segundo plano. Le notificaremos cuando esté listo.',
            'estimated_time' => 'Aproximadamente 1-5 minutos dependiendo del volumen de datos.',
        ], 202); // HTTP 202 Accepted
    }

    /**
     * Export sales summary to PDF.
     *
     * GET /api/reports/sales/pdf?start_date=2024-01-01&end_date=2024-12-31
     */
    public function salesPdf(Request $request): \Illuminate\Http\Response
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            abort(403, 'No tiene un negocio asociado.');
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Query orders
        $query = Order::where('tenant_id', $tenant->id)
            ->with(['items.product:id,name'])
            ->orderBy('created_at', 'desc');

        if (! empty($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }

        if (! empty($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        // Limit for PDF (prevent huge documents)
        $orders = $query->limit(500)->get();

        // Calculate summary
        $summary = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total'),
            'avg_order_value' => $orders->avg('total') ?? 0,
            'by_status' => $orders->groupBy('status')->map->count(),
            'by_payment' => $orders->groupBy(fn($o) => $o->payment_method?->value ?? 'unknown')->map->count(),
        ];

        $pdf = Pdf::loadView('reports.sales-summary', [
            'tenant' => $tenant,
            'orders' => $orders,
            'summary' => $summary,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'generated_at' => now(),
        ]);

        $filename = sprintf(
            'reporte_ventas_%s_%s.pdf',
            $tenant->slug ?? $tenant->id,
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Generate inventory check manifest PDF.
     *
     * GET /api/reports/inventory/pdf
     *
     * RESOURCE ESCALATION:
     * Large catalogs (>2000 SKUs) can exceed default memory limits.
     * JIT escalation prevents OOM errors during DOM rendering.
     */
    public function inventoryPdf(Request $request): \Illuminate\Http\Response
    {
        // ═══════════════════════════════════════════════════════════════════════
        // JIT RESOURCE ESCALATION: Prevent OOM on large catalogs
        // ═══════════════════════════════════════════════════════════════════════
        // These settings are scoped to THIS REQUEST ONLY.
        // They do not affect other requests or global PHP configuration.
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $tenant = $request->user()->tenant;

        if (! $tenant) {
            abort(403, 'No tiene un negocio asociado.');
        }

        // Get all active products with inventory tracking
        $products = $tenant->products()
            ->where('is_active', true)
            ->where('manage_stock', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold']);

        $pdf = Pdf::loadView('reports.inventory-manifest', [
            'tenant' => $tenant,
            'products' => $products,
            'low_stock_count' => $products->filter(fn($p) => $p->stock_quantity <= $p->low_stock_threshold)->count(),
            'out_of_stock_count' => $products->filter(fn($p) => $p->stock_quantity <= 0)->count(),
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = sprintf(
            'inventario_%s_%s.pdf',
            $tenant->slug ?? $tenant->id,
            now()->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Kitchen prep list PDF (orders in processing).
     *
     * GET /api/reports/kitchen/pdf
     */
    public function kitchenPdf(Request $request): \Illuminate\Http\Response
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            abort(403, 'No tiene un negocio asociado.');
        }

        // Get pending and processing orders
        $orders = Order::where('tenant_id', $tenant->id)
            ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING])
            ->with(['items.product:id,name'])
            ->orderBy('created_at', 'asc')
            ->get();

        $pdf = Pdf::loadView('reports.kitchen-prep', [
            'tenant' => $tenant,
            'orders' => $orders,
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('cocina_' . now()->format('Y-m-d_His') . '.pdf');
    }
}
