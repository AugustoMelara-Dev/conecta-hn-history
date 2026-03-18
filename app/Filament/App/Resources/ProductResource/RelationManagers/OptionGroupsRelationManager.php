<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
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
                // ═══ SECCIÓN: INFORMACIÓN DEL GRUPO ═══
                Forms\Components\Section::make('Configuración del Grupo')
                    ->description('Define cómo los clientes seleccionarán estas opciones')
                    ->icon('heroicon-o-squares-plus')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Grupo')
                            ->placeholder('Ej: Elige tu Tamaño, Salsas Extra')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\Select::make('type')
                            ->label('Tipo de Selección')
                            ->options([
                                'single' => '🔘 Selección Única (Radio)',
                                'multiple' => '☑️ Selección Múltiple (Checkbox)',
                            ])
                            ->default('single')
                            ->required()
                            ->helperText('Única: el cliente elige 1. Múltiple: puede elegir varias.')
                            ->live(),

                        Forms\Components\Toggle::make('is_required')
                            ->label('¿Es obligatorio?')
                            ->helperText('El cliente debe elegir al menos una opción')
                            ->default(false),

                        Forms\Components\TextInput::make('min_selections')
                            ->label('Mínimo de Opciones')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'multiple'),

                        Forms\Components\TextInput::make('max_selections')
                            ->label('Máximo de Opciones')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Sin límite')
                            ->helperText('Dejar vacío para ilimitado')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'multiple'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden de Aparición')
                            ->numeric()
                            ->default(0)
                            ->helperText('Menor número = aparece primero'),
                    ]),

                // ═══ SECCIÓN: OPCIONES DEL GRUPO (REPEATER) ═══
                Forms\Components\Section::make('Opciones Disponibles')
                    ->description('Agrega las opciones que el cliente puede seleccionar')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Forms\Components\Repeater::make('options')
                            ->relationship()
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la Opción')
                                    ->placeholder('Ej: Grande, Salsa BBQ, Aguacate')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('price_modifier')
                                    ->label('Modificador de Precio (L.)')
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.01)
                                    ->prefix('L.')
                                    ->helperText('+15.00 suma, -5.00 resta')
                                    ->columnSpan(1),

                                Forms\Components\Toggle::make('is_default')
                                    ->label('Preseleccionado')
                                    ->helperText('Marcar por defecto')
                                    ->default(false)
                                    ->inline(false)
                                    ->columnSpan(1),

                                Forms\Components\Toggle::make('is_available')
                                    ->label('Disponible')
                                    ->helperText('Desactivar si está agotado')
                                    ->default(true)
                                    ->inline(false)
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('sort_order')
                                    ->default(0),
                            ])
                            ->columns(5)
                            ->addActionLabel('➕ Agregar Opción')
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(1)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Nueva Opción'),
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
                        'single' => 'Única',
                        'multiple' => 'Múltiple',
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
                        'single' => 'Selección Única',
                        'multiple' => 'Selección Múltiple',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuevo Grupo de Opciones')
                    ->icon('heroicon-o-plus-circle'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton(),
                Tables\Actions\DeleteAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateHeading('Sin variantes configuradas')
            ->emptyStateDescription('Agrega grupos de opciones como "Tamaño" o "Extras" para este producto.')
            ->emptyStateIcon('heroicon-o-squares-plus');
    }
}
