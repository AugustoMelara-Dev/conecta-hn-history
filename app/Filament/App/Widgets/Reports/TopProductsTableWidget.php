<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopProductsTableWidget extends TableWidget
{
    protected static ?string $heading = 'Top productos';

    protected static ?int $sort = 8;

    public function table(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = Product::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId)->orderByDesc('sales_count')->limit(10);
        }

        return $table->query($query)
            ->columns([
                TextColumn::make('name')->label('Producto'),
                TextColumn::make('sales_count')->label('Ventas'),
                TextColumn::make('price')->label('Precio')->money('HNL'),
            ]);
    }
}
