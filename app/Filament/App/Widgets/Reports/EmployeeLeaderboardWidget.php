<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Illuminate\Support\Facades\DB;

use Filament\Facades\Filament;

use Filament\Widgets\TableWidget;

class EmployeeLeaderboardWidget extends TableWidget
{
    protected static ?string $heading = 'Leaderboard de equipo';
    protected static ?int $sort = 23;

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = DB::table('users as u')
            ->leftJoin('crm_deals as d', function ($join) {
                $join->on('d.owner_id', '=', 'u.id')->where('d.status', 'won');
            })
            ->selectRaw('u.id, u.name, COUNT(d.id) as deals_won, COALESCE(SUM(d.value), 0) as revenue')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('revenue')
            ->limit(10);
        if ($tenantId) $query->where('u.tenant_id', $tenantId);

        return $table->query($query)
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->label('Agente'),
                \Filament\Tables\Columns\TextColumn::make('deals_won')->label('Deals'),
                \Filament\Tables\Columns\TextColumn::make('revenue')->label('Ingresos')->money('HNL'),
            ]);
    }
}
