<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Application\Reporting\Reports;\n\nclass EmployeePerformanceReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::EmployeePerformance; }
    public function getName(): string { return 'Performance del equipo'; }
    public function getDescription(): string { return 'Performance de agentes de ventas.'; }
    public function getChartType(): string { return 'bar'; }
    public function getColumns(): array { return ['Agente', 'Deals cerrados', 'Ingresos generados']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $agents = DB::table('crm_deals as d')->join('users as u', 'u.id', '=', 'd.owner_id')
            ->where('d.tenant_id', $tenantId)->where('d.status', 'won')
            ->whereBetween('d.won_at', [$range->start, $range->end])
            ->selectRaw('u.id, u.name, COUNT(*) as cnt, SUM(d.value) as revenue')
            ->groupBy('u.id', 'u.name')->orderByDesc('revenue')->limit(20)->get();
        $rows = [];
        foreach ($agents as $a) {
            $rows[] = new ReportRow($a->name, (int) $a->cnt, unit: 'deals', metadata: ['revenue' => (float) $a->revenue]);
        }
        return new ReportData(rows: $rows, series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}\n
