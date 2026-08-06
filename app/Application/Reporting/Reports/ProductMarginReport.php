<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass ProductMarginReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::ProductMargin; }
    public function getName(): string { return 'Margen por producto'; }
    public function getDescription(): string { return 'Margen bruto por producto.'; }
    public function getChartType(): string { return 'bar'; }
    public function getColumns(): array { return ['Producto', 'Margen %', 'Margen (HNL)']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}\n
