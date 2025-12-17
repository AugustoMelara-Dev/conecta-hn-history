<?php

namespace App\Filament\App\Widgets;

use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * PopularProductsTable - Tabla de Productos Populares
 * 
 * Filament v4 Compatible: Extiende TableWidget.
 */
class PopularProductsTable extends BaseWidget
{
    protected static ?string $heading = 'Productos Más Vistos';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return 25;
    }

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->query(
                Product::query()
                    ->where('tenant_id', $tenant?->id)
                    ->orderBy('views_count', 'desc')
                    ->limit(5)
            )
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->circular()
                    ->size(40),

                TextColumn::make('name')
                    ->label('Producto')
                    ->weight('bold'),

                TextColumn::make('price')
                    ->label('Precio')
                    ->money('HNL'),

                TextColumn::make('views_count')
                    ->label('Vistas')
                    ->badge()
                    ->color('info'),
            ])
            ->paginated(false);
    }
}
