<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use App\Domain\Reporting\ValueObjects\ReportSeries;
use Illuminate\Support\Facades\DB;

class DiscountEffectivenessReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::DiscountEffectiveness;
    }

    public function getName(): string
    {
        return 'Efectividad de descuentos';
    }

    public function getDescription(): string
    {
        return 'ROI de cupones aplicados.';
    }

    public function getChartType(): string
    {
        return 'bar';
    }

    public function getColumns(): array
    {
        return ['Cupón', 'Usos', 'Descuento total (HNL)', 'Ingresos'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $coupons = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereNotNull('coupon_code')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw('coupon_code, COUNT(*) as uses, SUM(discount) as discount_total, SUM(total) as revenue')
            ->groupBy('coupon_code')
            ->orderByDesc('revenue')
            ->get();

        $rows = $coupons->map(static fn (object $coupon): ReportRow => new ReportRow(
            (string) $coupon->coupon_code,
            (float) $coupon->revenue,
            unit: 'HNL',
            metadata: [
                'uses' => (int) $coupon->uses,
                'discount_total' => (float) $coupon->discount_total,
                'revenue_per_discount' => (float) $coupon->discount_total > 0
                    ? round((float) $coupon->revenue / (float) $coupon->discount_total, 2)
                    : null,
            ],
        ))->all();

        return new ReportData(
            rows: $rows,
            series: [new ReportSeries(
                'Ingresos con cupón',
                $coupons->pluck('coupon_code')->map(static fn ($value): string => (string) $value)->all(),
                $coupons->pluck('revenue')->map(static fn ($value): float => (float) $value)->all(),
                'bar',
            )],
            summary: [
                'uses' => (int) $coupons->sum('uses'),
                'discount_total' => (float) $coupons->sum('discount_total'),
                'revenue' => (float) $coupons->sum('revenue'),
            ],
            metadata: ReportAvailability::metadata($range, ['source' => 'orders.coupon_code']),
        );
    }
}
