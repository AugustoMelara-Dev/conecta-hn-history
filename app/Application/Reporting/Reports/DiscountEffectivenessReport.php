<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass DiscountEffectivenessReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::DiscountEffectiveness; }
    public function getName(): string { return 'Efectividad de descuentos'; }
    public function getDescription(): string { return 'ROI de cupones aplicados.'; }
    public function getChartType(): string { return 'bar'; }
    public function getColumns(): array { return ['Cupón', 'Usos', 'Descuento total (HNL)', 'Ingresos']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}\n
