<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass SlowMoversReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::SlowMovers; }
    public function getName(): string { return 'Productos lentos'; }
    public function getDescription(): string { return 'Productos con baja rotación.'; }
    public function getChartType(): string { return 'table'; }
    public function getColumns(): array { return ['Producto', 'Stock', 'Última venta']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}\n
