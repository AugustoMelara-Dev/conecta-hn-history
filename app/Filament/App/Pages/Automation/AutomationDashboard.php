<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Automation;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * AutomationDashboard - Top 10 workflows by executions/day.
 */
class AutomationDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Automation Dashboard';

    protected static ?string $navigationGroup = 'Automatización';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.app.pages.automation-dashboard';

    public function getTopWorkflows(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
        if (! $tenantId) return [];

        return DB::table('workflow_runs as wr')
            ->join('workflow_definitions as wd', 'wd.id', '=', 'wr.workflow_definition_id')
            ->where('wr.tenant_id', $tenantId)
            ->where('wr.started_at', '>=', now()->subDays(30))
            ->selectRaw('wd.id, wd.name, COUNT(*) as executions, AVG(wr.duration_ms) as avg_duration')
            ->groupBy('wd.id', 'wd.name')
            ->orderByDesc('executions')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getStats(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
        if (! $tenantId) return ['total_workflows' => 0, 'active' => 0, 'runs_today' => 0, 'success_rate' => 0];

        return [
            'total_workflows' => DB::table('workflow_definitions')->where('tenant_id', $tenantId)->count(),
            'active' => DB::table('workflow_definitions')->where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'runs_today' => DB::table('workflow_runs')->where('tenant_id', $tenantId)
                ->whereDate('started_at', today())->count(),
            'success_rate' => $this->computeSuccessRate($tenantId),
        ];
    }

    private function computeSuccessRate(string $tenantId): float
    {
        $total = DB::table('workflow_runs')->where('tenant_id', $tenantId)
            ->whereIn('status', ['completed', 'failed'])->count();
        $success = DB::table('workflow_runs')->where('tenant_id', $tenantId)
            ->where('status', 'completed')->count();
        return $total > 0 ? round(($success / $total) * 100, 1) : 0;
    }
}
