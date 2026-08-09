<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Automation;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * AutomationDashboard - Top 10 workflows by executions/day.
 */
class AutomationDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $title = 'Automation Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Automatización';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.app.pages.automation-dashboard';

    public function getTopWorkflows(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return [];
        }

        return DB::table('automation_workflow_runs as wr')
            ->join('automation_workflow_definitions as wd', function ($join): void {
                $join->on('wd.workflow_key', '=', 'wr.workflow_key')
                    ->on('wd.tenant_id', '=', 'wr.tenant_id');
            })
            ->where('wr.tenant_id', $tenantId)
            ->where('wr.started_at', '>=', now()->subDays(30))
            ->selectRaw('wd.id, wd.workflow_key, COUNT(*) as executions')
            ->groupBy('wd.id', 'wd.workflow_key')
            ->orderByDesc('executions')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return ['total_workflows' => 0, 'active' => 0, 'runs_today' => 0, 'success_rate' => 0];
        }

        return [
            'total_workflows' => DB::table('automation_workflow_definitions')->where('tenant_id', $tenantId)->count(),
            'active' => DB::table('automation_workflow_definitions')->where('tenant_id', $tenantId)->whereNotNull('current_published_version_id')->count(),
            'runs_today' => DB::table('automation_workflow_runs')->where('tenant_id', $tenantId)
                ->whereDate('started_at', today())->count(),
            'success_rate' => $this->computeSuccessRate($tenantId),
        ];
    }

    private function computeSuccessRate(string $tenantId): float
    {
        $total = DB::table('automation_workflow_runs')->where('tenant_id', $tenantId)
            ->whereIn('status', ['completed', 'failed'])->count();
        $success = DB::table('automation_workflow_runs')->where('tenant_id', $tenantId)
            ->where('status', 'completed')->count();

        return $total > 0 ? round(($success / $total) * 100, 1) : 0;
    }
}
