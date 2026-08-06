<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass CustomerSegmentsReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::CustomerSegments; }
    public function getName(): string { return 'Segmentos de clientes'; }
    public function getDescription(): string { return 'Segmentación RFM.'; }
    public function getChartType(): string { return 'pie'; }
    public function getColumns(): array { return ['Segmento', 'Clientes', '%']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}\n
