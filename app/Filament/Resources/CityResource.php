<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Models\City;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-map-pin';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuracion';
    }

    public static function getModelLabel(): string
    {
        return 'Ciudad';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Ciudades';
    }

    /**
     * Los 18 Departamentos Oficiales de Honduras
     */
    public static function getDepartamentos(): array
    {
        return [
            'Atlantida' => 'Atlantida',
            'Colon' => 'Colon',
            'Comayagua' => 'Comayagua',
            'Copan' => 'Copan',
            'Cortes' => 'Cortes',
            'Choluteca' => 'Choluteca',
            'El Paraiso' => 'El Paraiso',
            'Francisco Morazan' => 'Francisco Morazan',
            'Gracias a Dios' => 'Gracias a Dios',
            'Intibuca' => 'Intibuca',
            'Islas de la Bahia' => 'Islas de la Bahia',
            'La Paz' => 'La Paz',
            'Lempira' => 'Lempira',
            'Ocotepeque' => 'Ocotepeque',
            'Olancho' => 'Olancho',
            'Santa Barbara' => 'Santa Barbara',
            'Valle' => 'Valle',
            'Yoro' => 'Yoro',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ═══════════════════════════════════════════════════════════
                // SECCION 1: Identidad y SEO
                // ═══════════════════════════════════════════════════════════
                Section::make('Identidad y URL Amigable (SEO)')
                    ->description('Configuracion para posicionamiento en buscadores')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la Ciudad')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, Set $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            })
                            ->helperText('Nombre oficial de la ciudad'),

                        TextInput::make('slug')
                            ->label('URL Amigable (Slug)')
                            ->required()
                            ->unique(City::class, 'slug', ignoreRecord: true)
                            ->prefix(config('app.url').'/')
                            ->helperText('Editable para optimizacion SEO. Ej: "tocoa" para conectahn.com/tocoa'),

                        Select::make('department')
                            ->label('Departamento')
                            ->options(static::getDepartamentos())
                            ->required()
                            ->searchable()
                            ->helperText('Los 18 departamentos oficiales de Honduras'),

                        TextInput::make('population')
                            ->label('Poblacion Estimada')
                            ->numeric()
                            ->suffix('habitantes')
                            ->helperText('Dato demografico para priorizar expansiones'),
                    ])->columns(2),

                // ═══════════════════════════════════════════════════════════
                // SECCION 2: Experiencia Visual Inmersiva (Hero Section)
                // ═══════════════════════════════════════════════════════════
                Section::make('Experiencia Visual (Landing Page)')
                    ->description('Contenido para la pagina de destino de la ciudad')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        FileUpload::make('hero_image')
                            ->label('Imagen Hero (Portada)')
                            ->image()
                            ->directory('cities/hero')
                            ->imageEditor()
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeTargetWidth('1920')
                            ->imageResizeTargetHeight('1080')
                            ->helperText('Imagen panoramica de alta resolucion (16:9). Recomendado: 1920x1080px'),

                        RichEditor::make('welcome_text')
                            ->label('Texto de Bienvenida (SEO)')
                            ->placeholder('Descubre los mejores restaurantes y servicios en [Ciudad]...')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'link',
                                'bulletList',
                                'orderedList',
                            ])
                            ->helperText('Contenido optimizado para SEO local. Incluir palabras clave de la ciudad.')
                            ->columnSpanFull(),

                        TextInput::make('meta_title')
                            ->label('Meta Title (SEO)')
                            ->maxLength(70)
                            ->placeholder('Negocios en [Ciudad] | Conecta HN')
                            ->helperText('Titulo para Google. Max 70 caracteres.'),

                        TextInput::make('meta_description')
                            ->label('Meta Description (SEO)')
                            ->maxLength(160)
                            ->placeholder('Encuentra restaurantes, tiendas y servicios en [Ciudad], Honduras...')
                            ->helperText('Descripcion para Google. Max 160 caracteres.'),
                    ])->columns(2),

                // ═══════════════════════════════════════════════════════════
                // SECCION 3: Control de Lanzamiento y Expansion
                // ═══════════════════════════════════════════════════════════
                Section::make('Control de Expansion')
                    ->description('Gestiona el lanzamiento de nuevas ciudades')
                    ->icon('heroicon-o-rocket-launch')
                    ->schema([
                        Toggle::make('is_available')
                            ->label('Ciudad Disponible para Registro')
                            ->default(true)
                            ->helperText('Si esta desactivado, los nuevos negocios NO podran seleccionar esta ciudad durante el registro.'),

                        Toggle::make('is_featured')
                            ->label('Ciudad Destacada')
                            ->default(false)
                            ->helperText('Mostrar en la seccion de ciudades destacadas en la pagina principal.'),

                        TextInput::make('launch_order')
                            ->label('Orden de Prioridad')
                            ->numeric()
                            ->default(0)
                            ->helperText('Orden en que aparece en los selectores (menor = primero)'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Imagen Hero como miniatura
                Tables\Columns\ImageColumn::make('hero_image')
                    ->label('')
                    ->circular()
                    ->size(45)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&background=random'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (City $record): string => $record->slug ?? ''),

                Tables\Columns\TextColumn::make('department')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('Negocios')
                    ->counts('tenants')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\ToggleColumn::make('is_available')
                    ->label('Disponible')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Destacada')
                    ->sortable(),

                Tables\Columns\TextColumn::make('launch_order')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->label('Departamento')
                    ->options(static::getDepartamentos())
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Disponibilidad')
                    ->trueLabel('Disponibles')
                    ->falseLabel('No Disponibles'),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Destacadas')
                    ->trueLabel('Destacadas')
                    ->falseLabel('No Destacadas'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('launch_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
