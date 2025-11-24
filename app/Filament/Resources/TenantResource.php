<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $modelPolicy = \App\Policies\TenantPolicy::class;

    // Use methods instead of properties for v4 compatibility
    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-building-storefront';
    }

    public static function getNavigationLabel(): string
    {
        return 'Negocios';
    }

    public static function getModelLabel(): string
    {
        return 'Negocio';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Negocios';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Información Principal')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Negocio')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Tenant::class, 'slug', ignoreRecord: true),

                        Forms\Components\Select::make('user_id')
                            ->label('Propietario')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Configuración de Plan')
                    ->schema([
                        Forms\Components\Toggle::make('is_pro')
                            ->label('Plan PRO Activo')
                            ->default(false),

                        Forms\Components\TextInput::make('product_limit')
                            ->label('Límite de Productos')
                            ->numeric()
                            ->default(10)
                            ->minValue(0)
                            ->required()
                            ->helperText('Cantidad máxima de productos visibles. Default: 10.'),
                    ])->columns(2),

                Forms\Components\Section::make('Apariencia del Micrositio')
                    ->description('Personaliza los colores y fuentes de la tienda pública.')
                    ->schema([
                        Forms\Components\ColorPicker::make('theme_config.primary_color')
                            ->label('Color Principal')
                            ->default('#000000')
                            ->helperText('Color hexadecimal para botones y acentos.'),

                        Forms\Components\TextInput::make('theme_config.radius')
                            ->label('Radio de Bordes')
                            ->default('0.5rem')
                            ->placeholder('0.5rem'),

                        Forms\Components\Select::make('theme_config.font')
                            ->label('Tipografía')
                            ->options([
                                'Inter' => 'Inter (Seria/Clean)',
                                'Roboto' => 'Roboto (Standard)',
                                'Poppins' => 'Poppins (Moderna)',
                            ])
                            ->default('Inter'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Ciudad')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_pro')
                    ->label('PRO')
                    ->boolean(),
                Tables\Columns\TextColumn::make('product_limit')
                    ->label('Límite')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_pro')->label('Solo PRO'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
