<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass InventoryTurnoverReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::InventoryTurnover; }
    public function getName(): string { return 'Rotación de inventario'; }
    public function getDescription(): string { return 'Veces que rota el inventario por período.'; }
    public function getChartType(): string { return 'gauge'; }
    public function getColumns(): array { return ['Categoría', 'Rotación']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}\n
