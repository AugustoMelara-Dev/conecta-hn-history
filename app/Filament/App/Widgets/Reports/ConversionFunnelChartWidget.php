<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass ConversionFunnelChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Embudo de conversión';
    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return ['datasets' => [], 'labels' => []];
        $stages = ['lead', 'qualified', 'proposal', 'negotiation', 'won'];
        $counts = [];
        foreach ($stages as $stage) {
            $counts[] = DB::table('crm_contacts')->where('tenant_id', $tenantId)->where('lifecycle_stage', $stage)->count();
        }
        return [
            'datasets' => [['label' => 'Contactos', 'data' => $counts, 'backgroundColor' => '#4F46E5']],
            'labels' => array_map('ucfirst', $stages),
        ];
    }

    protected function getType(): string { return 'bar'; }
}\n
