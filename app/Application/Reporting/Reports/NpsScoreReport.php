<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass NpsScoreReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::NpsScore; }
    public function getName(): string { return 'NPS score'; }
    public function getDescription(): string { return 'Net Promoter Score.'; }
    public function getChartType(): string { return 'gauge'; }
    public function getColumns(): array { return ['Métrica', 'Valor']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [new ReportRow('NPS', 0, unit: 'score')], series: [], summary: ['placeholder' => true], metadata: ['range' => $range->toArray()]);
    }
}\n
