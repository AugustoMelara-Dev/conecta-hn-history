<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SlowMoversTableWidget extends TableWidget
{
    protected static ?string $heading = 'Productos lentos';

    protected static ?int $sort = 9;

    public function table(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->getKey();
        $query = Product::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId)->where('stock', '>', 0)->orderBy('last_sale_at', 'asc')->limit(10);
        }

        return $table->query($query)
            ->columns([
                TextColumn::make('name')->label('Producto'),
                TextColumn::make('stock')->label('Stock'),
            ]);
    }
}
