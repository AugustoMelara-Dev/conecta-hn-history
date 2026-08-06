<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass WinRateReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::WinRate; }
    public function getName(): string { return 'Win rate'; }
    public function getDescription(): string { return 'Porcentaje de deals ganados.'; }
    public function getChartType(): string { return 'gauge'; }
    public function getColumns(): array { return ['Métrica', 'Valor']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $closed = DB::table('crm_deals')->where('tenant_id', $tenantId)->whereIn('status', ['won', 'lost'])->whereBetween('updated_at', [$range->start, $range->end])->count();
        $won = DB::table('crm_deals')->where('tenant_id', $tenantId)->where('status', 'won')->whereBetween('won_at', [$range->start, $range->end])->count();
        $rate = $closed > 0 ? ($won / $closed) * 100 : 0;
        return new ReportData(
            rows: [new ReportRow('Win rate', round($rate, 2), unit: '%'), new ReportRow('Total cerrados', $closed), new ReportRow('Total ganados', $won)],
            series: [],
            summary: ['win_rate' => round($rate, 2)],
            metadata: ['range' => $range->toArray()],
        );
    }
}\n
