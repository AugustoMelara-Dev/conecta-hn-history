<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class SlowMoversTableWidget extends TableWidget
{
    protected static ?string $heading = 'Productos lentos';

    protected static ?int $sort = 9;

    public function table(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = DB::table('products as p')
            ->leftJoin('order_items as oi', 'oi.product_id', '=', 'p.id')
            ->leftJoin('orders as o', function ($join) use ($tenantId): void {
                $join->on('o.id', '=', 'oi.order_id')
                    ->where('o.payment_status', 'paid')
                    ->where('o.created_at', '>=', now()->subDays(30));
                if ($tenantId) {
                    $join->where('o.tenant_id', $tenantId);
                }
            })
            ->selectRaw('p.id, p.name, p.stock_quantity, COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) as sold_units, MAX(o.created_at) as last_paid_sale_at')
            ->where('p.manage_stock', true)
            ->where('p.stock_quantity', '>', 0)
            ->groupBy('p.id', 'p.name', 'p.stock_quantity')
            ->orderBy('sold_units')
            ->orderByDesc('p.stock_quantity')
            ->limit(10);
        $tenantId
            ? $query->where('p.tenant_id', $tenantId)
            : $query->whereRaw('1 = 0');

        return $table->query($query)
            ->columns([
                TextColumn::make('name')->label('Producto'),
                TextColumn::make('stock_quantity')->label('Stock'),
                TextColumn::make('sold_units')->label('Vendidas (30 días)'),
                TextColumn::make('last_paid_sale_at')->label('Última venta')->dateTime('d/m/Y'),
            ]);
    }
}
