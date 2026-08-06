<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * SystemMetrics - Server health, multi-tenant.
 */
class SystemMetrics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $navigationGroup = 'Plataforma';
    protected static ?string $navigationLabel = 'Métricas';
    protected static ?string $title = 'System Metrics';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.admin.pages.system-metrics';

    public function getMetrics(): array
    {
        return [
            'tenants_count' => DB::table('tenants')->count(),
            'users_count' => DB::table('users')->count(),
            'orders_count' => DB::table('orders')->count(),
            'crm_contacts_count' => DB::table('crm_contacts')->count(),
            'crm_deals_count' => DB::table('crm_deals')->count(),
            'ai_runs_today' => DB::table('ai_assistant_runs')
                ->whereDate('created_at', today())->count(),
        ];
    }
}
