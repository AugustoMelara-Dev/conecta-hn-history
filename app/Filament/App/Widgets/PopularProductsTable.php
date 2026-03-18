<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Product;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PopularProductsTable extends BaseWidget
{
    protected static ?string $heading = 'Productos Mas Vistos';

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
        $query = Product::query()->whereRaw('1 = 0');

        if ($tenant instanceof Tenant) {
            $query = Product::query()
                ->where('tenant_id', (string) $tenant->getKey())
                ->orderBy('views_count', 'desc')
                ->limit(5);
        }

        return $table
            ->query($query)
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
