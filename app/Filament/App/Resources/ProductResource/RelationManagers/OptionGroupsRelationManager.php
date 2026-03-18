<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OptionGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'optionGroups';

    protected static ?string $title = 'Variantes y Extras';

    protected static ?string $modelLabel = 'Grupo de Opciones';

    protected static ?string $pluralModelLabel = 'Grupos de Opciones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Configuracion del Grupo')
                    ->description('Define como los clientes seleccionaran estas opciones.')
                    ->icon('heroicon-o-squares-plus')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Grupo')
                            ->placeholder('Ej: Elige tu tamano, salsas extra')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Select::make('type')
                            ->label('Tipo de Seleccion')
                            ->options([
                                'single' => 'Seleccion unica',
                                'multiple' => 'Seleccion multiple',
                            ])
                            ->default('single')
                            ->required()
                            ->helperText('Unica: el cliente elige 1. Multiple: puede elegir varias.')
                            ->live(),
                        Toggle::make('is_required')
                            ->label('Es obligatorio?')
                            ->helperText('El cliente debe elegir al menos una opcion.')
                            ->default(false),
                        TextInput::make('min_selections')
                            ->label('Minimo de Opciones')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->visible(fn (Get $get): bool => $get('type') === 'multiple'),
                        TextInput::make('max_selections')
                            ->label('Maximo de Opciones')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Sin limite')
                            ->helperText('Dejar vacio para ilimitado.')
                            ->visible(fn (Get $get): bool => $get('type') === 'multiple'),
                        TextInput::make('sort_order')
                            ->label('Orden de Aparicion')
                            ->numeric()
                            ->default(0)
                            ->helperText('Menor numero = aparece primero'),
                    ]),
                Section::make('Opciones Disponibles')
                    ->description('Agrega las opciones que el cliente puede seleccionar.')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Repeater::make('options')
                            ->relationship()
                            ->label('')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre de la Opcion')
                                    ->placeholder('Ej: Grande, Salsa BBQ, Aguacate')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('price_modifier')
                                    ->label('Modificador de Precio (L.)')
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.01)
                                    ->prefix('L.')
                                    ->helperText('+15.00 suma, -5.00 resta.')
                                    ->columnSpan(1),
                                Toggle::make('is_default')
                                    ->label('Preseleccionado')
                                    ->helperText('Marcar por defecto')
                                    ->default(false)
                                    ->inline(false)
                                    ->columnSpan(1),
                                Toggle::make('is_available')
                                    ->label('Disponible')
                                    ->helperText('Desactivar si esta agotado')
                                    ->default(true)
                                    ->inline(false)
                                    ->columnSpan(1),
                                Hidden::make('sort_order')
                                    ->default(0),
                            ])
                            ->columns(5)
                            ->addActionLabel('Agregar Opcion')
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(1)
                            ->itemLabel(fn (array $state): string => (string) ($state['name'] ?? 'Nueva Opcion')),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-squares-plus'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'single' => 'Unica',
                        'multiple' => 'Multiple',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'single' => 'info',
                        'multiple' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('options_count')
                    ->label('Opciones')
                    ->counts('options')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (int $state): string => "{$state} opciones"),
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Obligatorio')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'single' => 'Seleccion Unica',
                        'multiple' => 'Seleccion Multiple',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo Grupo de Opciones')
                    ->icon('heroicon-o-plus-circle'),
            ])
            ->actions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateHeading('Sin variantes configuradas')
            ->emptyStateDescription('Agrega grupos de opciones como tamano o extras para este producto.')
            ->emptyStateIcon('heroicon-o-squares-plus');
    }
}
