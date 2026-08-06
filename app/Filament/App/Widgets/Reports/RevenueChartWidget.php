<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ingresos por mes';
    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return ['datasets' => [], 'labels' => []];

        $data = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, SUM(total_amount) as revenue")
            ->groupBy('month')->orderBy('month')->get();

        return [
            'datasets' => [[
                'label' => 'Ingresos',
                'data' => $data->pluck('revenue')->map(fn ($v) => (float) $v)->toArray(),
                'backgroundColor' => 'rgba(79, 70, 229, 0.1)',
                'borderColor' => '#4F46E5',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string { return 'line'; }
}\n
