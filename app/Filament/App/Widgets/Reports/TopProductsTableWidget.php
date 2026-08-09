<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopProductsTableWidget extends TableWidget
{
    protected static ?string $heading = 'Top productos';

    protected static ?int $sort = 8;

    public function table(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = DB::table('products as p')
            ->leftJoin('order_items as oi', 'oi.product_id', '=', 'p.id')
            ->leftJoin('orders as o', function ($join) use ($tenantId): void {
                $join->on('o.id', '=', 'oi.order_id')
                    ->where('o.payment_status', 'paid');
                if ($tenantId) {
                    $join->where('o.tenant_id', $tenantId);
                }
            })
            ->selectRaw('p.id, p.name, p.price, COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) as sold_units, COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.line_total ELSE 0 END), 0) as revenue')
            ->groupBy('p.id', 'p.name', 'p.price')
            ->orderByDesc('revenue')
            ->limit(10);
        $tenantId
            ? $query->where('p.tenant_id', $tenantId)
            : $query->whereRaw('1 = 0');

        return $table->query($query)
            ->columns([
                TextColumn::make('name')->label('Producto'),
                TextColumn::make('sold_units')->label('Unidades'),
                TextColumn::make('revenue')->label('Ingresos')->money('HNL'),
                TextColumn::make('price')->label('Precio')->money('HNL'),
            ]);
    }
}
