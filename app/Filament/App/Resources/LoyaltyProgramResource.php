<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\LoyaltyProgramResource\Pages;
use App\Models\LoyaltyProgram;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyProgramResource extends Resource
{
    protected static ?string $model = LoyaltyProgram::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Fidelizacion';

    protected static ?string $navigationLabel = 'Configuracion';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Reglas del Programa')
                    ->description('Define como tus clientes ganan y canjean puntos.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Programa')
                            ->required()
                            ->maxLength(255)
                            ->default('Programa de Puntos'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('earn_ratio')
                                    ->label('Gasto para ganar 1 punto (L.)')
                                    ->helperText('Si pones 10, el cliente gana 1 punto por cada L.10 gastados.')
                                    ->numeric()
                                    ->default(10)
                                    ->required()
                                    ->minValue(1),

                                TextInput::make('redemption_ratio')
                                    ->label('Puntos para L.1 de descuento')
                                    ->helperText('Si pones 100, el cliente necesita 100 puntos para tener L.1 de descuento.')
                                    ->numeric()
                                    ->default(100)
                                    ->required()
                                    ->minValue(1),
                            ]),

                        TextInput::make('expiry_days')
                            ->label('Vencimiento de Puntos (Dias)')
                            ->numeric()
                            ->default(365)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Programa Activo')
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('earn_ratio')
                    ->label('Ratio Ganancia')
                    ->prefix('L. ')
                    ->suffix(' = 1 Pto'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyPrograms::route('/'),
            'create' => Pages\CreateLoyaltyProgram::route('/create'),
            'edit' => Pages\EditLoyaltyProgram::route('/{record}/edit'),
        ];
    }
}
