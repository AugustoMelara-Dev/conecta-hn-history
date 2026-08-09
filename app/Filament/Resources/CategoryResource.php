<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    // Strict Governance: Global Categories are managed ONLY by Super Admin
    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static ?string $model = Category::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function getModelLabel(): string
    {
        return 'Categoria';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Categorias';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuracion';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ═══════════════════════════════════════════════════════════
                // SECCION 1: Identidad de la Categoria
                // ═══════════════════════════════════════════════════════════
                Section::make('Identidad de la Categoria')
                    ->description('Nombre y URL amigable para SEO')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la Categoria')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('slug', Str::slug($state));
                            })
                            ->helperText('Nombre visible para los usuarios (ej: Restaurantes)'),

                        TextInput::make('slug')
                            ->label('Slug SEO')
                            ->required()
                            ->unique(Category::class, 'slug', ignoreRecord: true)
                            ->prefix(config('app.url').'/categoria/')
                            ->helperText('URL amigable generada automaticamente'),
                    ])->columns(2),

                // ═══════════════════════════════════════════════════════════
                // SECCION 2: Identidad Visual (Branding)
                // ═══════════════════════════════════════════════════════════
                Section::make('Identidad Visual')
                    ->description('Color e icono para la interfaz de usuario')
                    ->icon('heroicon-o-paint-brush')
                    ->schema([
                        ColorPicker::make('color')
                            ->label('Color de la Categoria')
                            ->required()
                            ->default('#3b82f6')
                            ->helperText('Codigo HEX para etiquetas y pines del mapa (ej: Rojo para Restaurantes, Verde para Salud)'),

                        TextInput::make('icon')
                            ->label('Icono (Heroicons/Lucide)')
                            ->placeholder('heroicon-o-cake o utensils')
                            ->helperText('Nombre del icono para renderizado dinamico. Ver: heroicons.com o lucide.dev/icons'),

                        FileUpload::make('icon_svg')
                            ->label('Icono personalizado (opcional)')
                            ->acceptedFileTypes(['image/png', 'image/webp'])
                            ->directory('categories/icons')
                            ->helperText('Alternativa al icono de texto. Usa PNG o WebP; los SVG subidos se bloquean por seguridad.'),
                    ])->columns(3),

                // ═══════════════════════════════════════════════════════════
                // SECCION 3: Motor de Priorizacion y Ranking
                // ═══════════════════════════════════════════════════════════
                Section::make('Priorizacion y Merchandising')
                    ->description('Control de orden y visibilidad para campanas estacionales')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Orden de Prioridad')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->helperText('Menor numero = Mayor prioridad. Ej: "Floristerias" = 1 en San Valentin.'),

                        Toggle::make('is_active')
                            ->label('Categoria Activa')
                            ->default(true)
                            ->helperText('Desactiva para ocultar categorias estacionales sin perder datos.'),

                        Toggle::make('is_featured')
                            ->label('Categoria Destacada')
                            ->default(false)
                            ->helperText('Mostrar en la seccion de categorias destacadas del home.'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Color Visual
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->copyable()
                    ->tooltip(fn ($state) => $state),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Nombre con Slug
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('name')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Category $record): string => '/'.$record->slug),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Icono
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('icon')
                    ->label('Icono')
                    ->badge()
                    ->color('info')
                    ->placeholder('Sin icono'),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Sort Order (Editable en linea)
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextInputColumn::make('sort_order')
                    ->label('Orden')
                    ->type('number')
                    ->sortable()
                    ->rules(['required', 'integer', 'min:0']),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Conteo de negocios
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('Negocios')
                    ->counts('tenants')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                // ═══════════════════════════════════════════════════════════
                // COLUMNA: Estado Activo (Toggle en linea)
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activa')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Destacada')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Destacadas')
                    ->trueLabel('Destacadas')
                    ->falseLabel('No Destacadas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            // ═══════════════════════════════════════════════════════════════
            // ORDENAMIENTO POR DEFECTO: Respeta la jerarquia de sort_order
            // ═══════════════════════════════════════════════════════════════
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
