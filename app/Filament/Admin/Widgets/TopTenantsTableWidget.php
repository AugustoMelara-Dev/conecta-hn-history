<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopTenantsTableWidget extends TableWidget
{
    protected static ?string $heading = 'Top tenants';

    protected static ?int $sort = 4;

    public function table(Tables\Table $table): Tables\Table
    {
        $data = DB::table('orders')
            ->selectRaw('tenant_id, COUNT(*) as orders_count, SUM(total_amount) as revenue')
            ->where('status', 'paid')
            ->groupBy('tenant_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($r) => (object) [
                'tenant_id' => $r->tenant_id,
                'name' => DB::table('tenants')->where('id', $r->tenant_id)->value('name') ?? 'Unknown',
                'orders_count' => (int) $r->orders_count,
                'revenue' => (float) $r->revenue,
            ]);

        return $table
            ->records($data)
            ->columns([
                TextColumn::make('name')->label('Tenant'),
                TextColumn::make('orders_count')->label('Órdenes'),
                TextColumn::make('revenue')->label('Ingresos')->money('HNL'),
            ]);
    }
}
