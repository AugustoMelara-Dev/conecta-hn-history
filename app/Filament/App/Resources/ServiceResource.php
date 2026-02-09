<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Catálogo de Servicios';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Servicio')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Servicio')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Precio')
                                    ->numeric()
                                    ->prefix('L.')
                                    ->required(),

                                Forms\Components\TextInput::make('duration_minutes')
                                    ->label('Duración')
                                    ->numeric()
                                    ->suffix('minutos')
                                    ->required()
                                    ->step(5)
                                    ->default(30),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Servicio Activo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Servicio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('formatted_price')
                    ->label('Precio'),

                Tables\Columns\TextColumn::make('formatted_duration')
                    ->label('Duración')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
