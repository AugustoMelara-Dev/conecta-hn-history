<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class EmployeeLeaderboardWidget extends TableWidget
{
    protected static ?string $heading = 'Leaderboard de equipo';

    protected static ?int $sort = 23;

    public function table(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = DB::table('users as u')
            ->leftJoin('crm_deals as d', function ($join) {
                $join->on('d.owner_user_id', '=', 'u.id')->where('d.status', 'won');
            })
            ->selectRaw('u.id, u.name, COUNT(d.id) as deals_won, COALESCE(SUM(d.value), 0) as revenue')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('revenue')
            ->limit(10);
        $tenantId
            ? $query->where('u.tenant_id', $tenantId)
            : $query->whereRaw('1 = 0');

        return $table->query($query)
            ->columns([
                TextColumn::make('name')->label('Agente'),
                TextColumn::make('deals_won')->label('Deals'),
                TextColumn::make('revenue')->label('Ingresos')->money('HNL'),
            ]);
    }
}
