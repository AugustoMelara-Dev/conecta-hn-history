<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Markets\TenantMarketResolver;
use App\Contracts\ReportGenerationInterface;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\VerifiedCommercialOutcome;
use Barryvdh\DomPDF\Facade\Pdf;

final class ReportGenerationService implements ReportGenerationInterface
{
    public function __construct(
        private TenantMarketResolver $marketResolver,
    ) {}

    /**
     * Generates a raw PDF string for the sales report.
     */
    public function generateSalesPdf(Tenant $tenant, array $filters): string
    {
        $currency = $this->marketResolver->resolve($tenant)->currencyCode;
        $query = Order::where('tenant_id', $tenant->id)
            ->with(['items.product:id,name'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Limit for PDF (prevent huge documents)
        $orders = $query->limit(500)->get();

        $orderIds = $orders->modelKeys();
        $verifiedRevenueMinor = $orderIds === []
            ? 0
            : (int) VerifiedCommercialOutcome::query()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where('aggregate_type', 'order')
                ->whereIn('aggregate_id', array_map('strval', $orderIds))
                ->whereIn('outcome', [
                    CommercialOutcome::OrderPaid->value,
                    CommercialOutcome::OrderRefunded->value,
                ])
                ->where('currency', $currency)
                ->sum('amount_minor');
        $verifiedPaidOrders = $orderIds === []
            ? 0
            : VerifiedCommercialOutcome::query()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where('aggregate_type', 'order')
                ->whereIn('aggregate_id', array_map('strval', $orderIds))
                ->where('outcome', CommercialOutcome::OrderPaid->value)
                ->where('currency', $currency)
                ->distinct()
                ->count('aggregate_id');

        // Financial fields are derived from verified payment projections only.
        $summary = [
            'currency' => $currency,
            'total_orders' => $orders->count(),
            'total_revenue' => $verifiedRevenueMinor / 100,
            'avg_order_value' => $verifiedPaidOrders > 0
                ? ($verifiedRevenueMinor / 100) / $verifiedPaidOrders
                : 0,
            'by_status' => $orders->groupBy('status')->map->count(),
            'by_payment' => $orders->groupBy(fn ($o) => $o->payment_method?->value ?? 'unknown')->map->count(),
        ];

        return Pdf::loadView('reports.sales-summary', [
            'tenant' => $tenant,
            'orders' => $orders,
            'summary' => $summary,
            'start_date' => $filters['start_date'] ?? null,
            'end_date' => $filters['end_date'] ?? null,
            'generated_at' => now(),
        ])->output();
    }

    /**
     * Generates a raw PDF string for the inventory report.
     */
    public function generateInventoryPdf(Tenant $tenant): string
    {
        $products = $tenant->products()
            ->where('is_active', true)
            ->where('manage_stock', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold']);

        $pdf = Pdf::loadView('reports.inventory-manifest', [
            'tenant' => $tenant,
            'products' => $products,
            'low_stock_count' => $products->filter(fn ($p) => $p->stock_quantity <= $p->low_stock_threshold)->count(),
            'out_of_stock_count' => $products->filter(fn ($p) => $p->stock_quantity <= 0)->count(),
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generates a raw PDF string for the kitchen report.
     */
    public function generateKitchenPdf(Tenant $tenant): string
    {
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

        return $pdf->output();
    }
}
