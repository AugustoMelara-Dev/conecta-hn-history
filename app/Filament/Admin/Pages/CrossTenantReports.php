<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Illuminate\Support\Facades\DB;

/**
 * CrossTenantReports - Aggregate over tenants.
 */
class CrossTenantReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'CRM Global';
    protected static ?string $navigationLabel = 'Cross-tenant';
    protected static ?string $title = 'Cross-Tenant Reports';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.admin.pages.cross-tenant-reports';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $schema->schema([
            Section::make('Métricas cross-tenant')->schema([
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
        return [
            'total_orders' => DB::table('orders')->count(),
            'total_revenue' => DB::table('orders')->where('status', 'paid')->sum('total_amount') ?? 0,
            'total_users' => DB::table('users')->count(),
            'total_ai_calls' => DB::table('ai_assistant_runs')->count(),
            'total_tenants' => DB::table('tenants')->count(),
            'total_crm_contacts' => DB::table('crm_contacts')->count(),
            'total_crm_deals' => DB::table('crm_deals')->count(),
        ];
    }
}


