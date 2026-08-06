<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass CohortRetentionReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::CohortRetention; }
    public function getName(): string { return 'Retención por cohorte'; }
    public function getDescription(): string { return 'Retención de cohortes mensuales.'; }
    public function getChartType(): string { return 'heatmap'; }
    public function getColumns(): array { return ['Cohorte', 'Mes 0', 'Mes 1', 'Mes 2', 'Mes 3']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: ['placeholder' => true], metadata: ['range' => $range->toArray()]);
    }
}\n
