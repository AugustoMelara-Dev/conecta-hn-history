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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory';
    }

    public static function getNavigationLabel(): string
    {
        return 'Inventory Movements';
    }

    public static function getModelLabel(): string
    {
        return 'Inventory Movement';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Inventory Movements';
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
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof \BackedEnum ? $state->value : (string) $state),
                Tables\Columns\TextColumn::make('quantity_delta')
                    ->label('Delta')
                    ->numeric(decimalPlaces: 3),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(30),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([]);
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
