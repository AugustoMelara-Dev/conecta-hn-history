<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\InventoryMovementResource\Pages;
use App\Filament\Traits\ScopesToTenant;
use App\Models\InventoryMovement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryMovementResource extends Resource
{
    use ScopesToTenant;

    protected static ?string $model = InventoryMovement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return 'Inventario';
    }

    public static function getNavigationLabel(): string
    {
        return 'Movimientos';
    }

    public static function getModelLabel(): string
    {
        return 'Movimiento';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Movimientos';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        $value = $state instanceof \BackedEnum ? $state->value : (string) $state;

                        return match ($value) {
                            'initial' => 'Carga inicial',
                            'purchase' => 'Compra',
                            'sale' => 'Venta',
                            'restock_return' => 'Devolucion a stock',
                            'manual_adjustment' => 'Ajuste manual',
                            'loss' => 'Merma',
                            'transfer' => 'Traslado',
                            default => $value,
                        };
                    }),
                Tables\Columns\TextColumn::make('quantity_delta')
                    ->label('Cambio')
                    ->numeric(decimalPlaces: 3),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(30),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavia no hay movimientos de inventario',
                'description' => 'Aqui quedaran registrados los ajustes, ventas, compras, mermas y traslados de stock.',
            ]));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryMovements::route('/'),
        ];
    }
}
