<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

/**
 * CrossTenantReports - Aggregate over tenants.
 */
class CrossTenantReports extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM Global';

    protected static ?string $navigationLabel = 'Cross-tenant';

    protected static ?string $title = 'Cross-Tenant Reports';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.admin.pages.cross-tenant-reports';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Métricas cross-tenant')->components([
                Select::make('metric')->label('Métrica')->options([
                    'total_orders' => 'Total órdenes',
                    'total_revenue' => 'Total ingresos',
                    'total_users' => 'Total usuarios',
                    'total_ai_calls' => 'Total AI calls',
                ]),
            ]),
        ])->statePath('data');
    }

    public function getAggregateData(): array
    {
        $contactsTable = DatabaseSchema::hasTable('contacts') ? 'contacts' : 'crm_contacts';

        return [
            'total_orders' => $this->countTable('orders'),
            'total_revenue' => DatabaseSchema::hasTable('orders')
                ? (float) DB::table('orders')->where('status', 'paid')->sum('total_amount')
                : 0.0,
            'total_users' => $this->countTable('users'),
            'total_ai_calls' => $this->countTable('ai_assistant_runs'),
            'total_tenants' => $this->countTable('tenants'),
            'total_crm_contacts' => $this->countTable($contactsTable),
            'total_crm_deals' => $this->countTable('crm_deals'),
        ];
    }

    private function countTable(string $table): int
    {
        return DatabaseSchema::hasTable($table) ? DB::table($table)->count() : 0;
    }
}
