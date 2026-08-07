<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Facades\Filament;

use Filament\Widgets\TableWidget;

class SlowMoversTableWidget extends TableWidget
{
    protected static ?string $heading = 'Productos lentos';
    protected static ?int $sort = 9;

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = \App\Models\Product::query();
        if ($tenantId) $query->where('tenant_id', $tenantId)->where('stock', '>', 0)->orderBy('last_sale_at', 'asc')->limit(10);

        return $table->query($query)
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->label('Producto'),
                \Filament\Tables\Columns\TextColumn::make('stock')->label('Stock'),
            ]);
    }
}
