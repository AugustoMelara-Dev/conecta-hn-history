<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Facades\Filament;

use Filament\Widgets\TableWidget;

class TopProductsTableWidget extends TableWidget
{
    protected static ?string $heading = 'Top productos';
    protected static ?int $sort = 8;

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = \App\Models\Product::query();
        if ($tenantId) $query->where('tenant_id', $tenantId)->orderByDesc('sales_count')->limit(10);

        return $table->query($query)
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->label('Producto'),
                \Filament\Tables\Columns\TextColumn::make('sales_count')->label('Ventas'),
                \Filament\Tables\Columns\TextColumn::make('price')->label('Precio')->money('HNL'),
            ]);
    }
}
