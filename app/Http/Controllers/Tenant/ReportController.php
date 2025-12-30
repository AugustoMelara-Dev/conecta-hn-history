<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * ReportController - PDF Streaming Engine
 *
 * ARCHITECTURE: All PDFs streamed via DomPDF.
 * Method: Pdf::loadView(...)->stream() for browser preview.
 *
 * DATA SCOPE: All queries scoped to authenticated tenant.
 */
final class ReportController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════
    // INDEX - Reports Dashboard
    // ═══════════════════════════════════════════════════════════════════════
    public function index(Request $request): InertiaResponse
    {
        $tenant = $this->getTenant($request);

        return Inertia::render('Tenant/Reports/Index', [
            'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SALES PDF - Date-Ranged Sales Summary
    // ═══════════════════════════════════════════════════════════════════════
    public function salesPdf(Request $request): Response
    {
        $tenant = $this->getTenant($request);

        // Validate date range
        $validated = $request->validate([
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        // Fetch orders in range (tenant-scoped)
        $orders = Order::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'paid'])
            ->with('items')
            ->orderByDesc('created_at')
            ->get();

        // Calculate totals
        $totalRevenue = $orders->sum('total');
        $orderCount = $orders->count();
        $averageOrder = $orderCount > 0 ? $totalRevenue / $orderCount : 0;

        // Generate PDF and stream to browser
        $pdf = Pdf::loadView('reports.sales-summary', [
            'tenant' => $tenant,
            'orders' => $orders,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalRevenue' => $totalRevenue,
            'orderCount' => $orderCount,
            'averageOrder' => $averageOrder,
        ]);

        $filename = "ventas_{$tenant->slug}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}.pdf";

        return $pdf->stream($filename);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INVENTORY MANIFEST - Product Catalog PDF
    // ═══════════════════════════════════════════════════════════════════════
    public function inventoryPdf(Request $request): Response
    {
        $tenant = $this->getTenant($request);

        // Fetch all products (tenant-scoped)
        $products = $tenant->products()
            ->orderBy('name')
            ->get();

        // Calculate stats
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

        $filename = "inventario_{$tenant->slug}_" . now()->format('Y-m-d') . ".pdf";

        return $pdf->stream($filename);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function getTenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;
        abort_unless($tenant, 403, 'Tenant not found.');
        return $tenant;
    }
}
