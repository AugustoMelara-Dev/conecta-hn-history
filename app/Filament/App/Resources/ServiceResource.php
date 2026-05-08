<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $navigationGroup = 'Servicios';

    protected static ?string $navigationLabel = 'Catálogo de servicios';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'servicio';
    }

    public static function getPluralModelLabel(): string
    {
        return 'servicios';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del servicio')
                    ->description('Define el nombre, precio y duración que verán los clientes al reservar.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del servicio')
                            ->placeholder('Ej. Corte premium')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Explica qué incluye el servicio y cualquier preparación necesaria.')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Precio')
                                    ->numeric()
                                    ->prefix('L.')
                                    ->helperText('Monto en lempiras que se mostrará al cliente.')
                                    ->required(),

                                Forms\Components\TextInput::make('duration_minutes')
                                    ->label('Duración')
                                    ->numeric()
                                    ->suffix('minutos')
                                    ->helperText('Usa incrementos de 5 minutos para mantener la agenda ordenada.')
                                    ->required()
                                    ->step(5)
                                    ->default(30),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Disponible para reservas')
                            ->helperText('Desactívalo si no quieres que aparezca como opción reservable.')
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
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('formatted_price')
                    ->label('Precio')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('price', $direction)),

                Tables\Columns\TextColumn::make('formatted_duration')
                    ->label('Duración')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Disponible')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Disponibilidad')
                    ->placeholder('Todos los servicios')
                    ->trueLabel('Disponibles')
                    ->falseLabel('Ocultos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar servicio')
                    ->modalDescription('Esta acción quitará el servicio del catálogo de reservas. Las citas existentes no se modifican.')
                    ->modalSubmitActionLabel('Eliminar servicio'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar servicios seleccionados')
                        ->modalDescription('Esta acción quita los servicios seleccionados del catálogo. Verifica que no los necesites para nuevas reservas.')
                        ->modalSubmitActionLabel('Eliminar servicios'),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-scissors')
            ->emptyStateHeading('Aún no hay servicios')
            ->emptyStateDescription('Crea el primer servicio para que tus clientes puedan reservar desde el sitio público.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear servicio'),
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
