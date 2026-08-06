<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass ChannelPerformanceReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::ChannelPerformance; }
    public function getName(): string { return 'Performance por canal'; }
    public function getDescription(): string { return 'Ingresos por canal de venta.'; }
    public function getChartType(): string { return 'bar'; }
    public function getColumns(): array { return ['Canal', 'Ingresos (HNL)', 'Órdenes']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $channels = DB::table('orders')->where('tenant_id', $tenantId)->where('status', 'paid')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw('COALESCE(channel, "direct") as channel, SUM(total_amount) as revenue, COUNT(*) as cnt')
            ->groupBy('channel')->get();
        $rows = [];
        foreach ($channels as $c) {
            $rows[] = new ReportRow($c->channel, (float) $c->revenue, unit: 'HNL', metadata: ['count' => (int) $c->cnt]);
        }
        return new ReportData(rows: $rows, series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}\n
