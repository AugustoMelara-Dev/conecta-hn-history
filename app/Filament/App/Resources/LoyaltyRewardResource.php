<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\LoyaltyRewardResource\Pages;
use App\Models\LoyaltyReward;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyRewardResource extends Resource
{
    protected static ?string $model = LoyaltyReward::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|\UnitEnum|null $navigationGroup = 'Fidelización';

    protected static ?string $navigationLabel = 'Recompensas';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Detalles del Premio')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Premio')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Descripcion')
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('points_cost')
                                    ->label('Costo en Puntos')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),

                                TextInput::make('stock')
                                    ->label('Stock Disponible')
                                    ->helperText('Usa -1 para stock ilimitado')
                                    ->numeric()
                                    ->default(-1)
                                    ->required(),
                            ]),

                        Toggle::make('is_active')
                            ->label('Disponible para Canje')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('points_cost')
                    ->label('Costo')
                    ->badge()
                    ->color('warning')
                    ->suffix(' Ptos'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->formatStateUsing(fn (string $state): string => $state === '-1' ? 'infinito' : $state),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRewards::route('/'),
            'create' => Pages\CreateLoyaltyReward::route('/create'),
            'edit' => Pages\EditLoyaltyReward::route('/{record}/edit'),
        ];
    }
}
