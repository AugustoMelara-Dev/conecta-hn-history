<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Product\Contracts\ProductReader;
use App\Domain\Sales\Contracts\ReportReader;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * ReportController - PDF Report Generation (Enterprise Standards)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE:                                               ║
 * ║  - Zero Eloquent in controller (delegates to Readers)                    ║
 * ║  - Zero $request->user()->tenant access (uses TenantContext)             ║
 * ║  - OPTIMIZED: Passes preloaded orders to avoid double-query              ║
 * ║  - DECOUPLED: Uses ProductReader for inventory, ReportReader for sales   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportReader $reportReader,
        private readonly ProductReader $productReader,
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Reports dashboard.
     */
    public function index(Request $request): InertiaResponse
    {
        $tenant = (object) $this->tenantContext->getTenantData(['id', 'name', 'slug', 'is_pro']);

        return Inertia::render('Tenant/Reports/Index', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Generate sales PDF report.
     *
     * OPTIMIZATION: Single query - passes $orders to getSalesSummary.
     */
    public function salesPdf(Request $request): Response
    {
        $tenantId = $this->tenantContext->getTenantId();
        $tenant = (object) $this->tenantContext->getTenantData();

        $validated = $request->validate([
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        // OPTIMIZED: Single query - pass orders to summary calculation
        $orders = $this->reportReader->getSalesData($tenantId, $startDate, $endDate);
        $summary = $this->reportReader->getSalesSummary($tenantId, $startDate, $endDate, $orders);

        $pdf = Pdf::loadView('reports.sales-summary', [
            'tenant' => $tenant,
            'orders' => $orders,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalRevenue' => $summary['total_revenue'],
            'orderCount' => $summary['order_count'],
            'averageOrder' => $summary['average_order'],
        ]);

        $filename = "ventas_{$tenant->slug}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Generate inventory PDF report.
     *
     * DELEGATED: Uses ProductReader instead of $tenant->products()
     */
    public function inventoryPdf(Request $request): Response
    {
        $tenantId = $this->tenantContext->getTenantId();
        $tenant = (object) $this->tenantContext->getTenantData();

        // DELEGATED: Query logic in ProductReader, not controller
        $products = $this->productReader->getInventory($tenantId);

        $totalProducts = $products->count();
        $inStock = $products->where('stock_status', 'in_stock')->count();
        $lowStock = $products->where('stock_status', 'low_stock')->count();
        $outOfStock = $products->where('stock_status', 'out_of_stock')->count();

        $pdf = Pdf::loadView('reports.inventory-manifest', [
            'tenant' => $tenant,
            'products' => $products,
            'totalProducts' => $totalProducts,
            'inStock' => $inStock,
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'generatedAt' => now(),
        ]);

        $filename = "inventario_{$tenant->slug}_" . now()->format('Y-m-d') . '.pdf';

        return $pdf->stream($filename);
    }
}
