<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SystemMetrics - Server health, multi-tenant.
 */
class SystemMetrics extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string|\UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?string $navigationLabel = 'Métricas';

    protected static ?string $title = 'System Metrics';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.admin.pages.system-metrics';

    public function getMetrics(): array
    {
        $contactsTable = Schema::hasTable('contacts') ? 'contacts' : 'crm_contacts';

        return [
            'tenants_count' => $this->countTable('tenants'),
            'users_count' => $this->countTable('users'),
            'orders_count' => $this->countTable('orders'),
            'crm_contacts_count' => $this->countTable($contactsTable),
            'crm_deals_count' => $this->countTable('crm_deals'),
            'ai_runs_today' => Schema::hasTable('ai_assistant_runs')
                ? DB::table('ai_assistant_runs')->whereDate('created_at', today())->count()
                : 0,
        ];
    }

    private function countTable(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }
}
