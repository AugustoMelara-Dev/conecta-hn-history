<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationGroup(): ?string
    {
        return 'Gestión de Catálogo';
    }

    public static function getNavigationLabel(): string
    {
        return 'Productos';
    }

    public static function getModelLabel(): string
    {
        return 'Producto';
    }

    public static function form(Schema $schema): Schema
    {
        // Feature Gating: Check Plan
        $tenant = \Filament\Facades\Filament::getTenant();
        $isPro = $tenant && $tenant->plan_type === 'pro' || $tenant->plan_type === 'enterprise';

        return $schema
            ->schema([
                // 1. MAIN COLUMN (LEFT - 2/3)
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Información del Producto')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del Producto')
                                    ->required()
                                    ->maxLength(255),

                                // RichEditor with simplified toolbar to avoid messy HTML
                                Forms\Components\RichEditor::make('description')
                                    ->label('Descripción')
                                    ->toolbarButtons([
                                        'bold', 'italic', 'bulletList', 'orderedList', 'link', 'redo', 'undo',
                                    ])
                                    ->columnSpan('full'),
                            ]),

                        Forms\Components\Section::make('Galería de Imágenes')
                            ->schema([
                                Forms\Components\SpatieMediaLibraryFileUpload::make('images')
                                    ->label('Imágenes del Producto')
                                    ->collection('product-images')
                                    ->image()
                                    ->imageEditor()
                                    ->reorderable()
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']) // Security Hardening (VC-AUDIT-003)
                                    ->maxSize(2048) // 2MB Limit
                                    ->maxFiles(5)
                                    ->columnSpan('full'),
                            ]),

                        // GATED FEATURE: Modifiers (Only for PRO/ENT)
                        Forms\Components\Section::make('Opciones y Modificadores')
                            ->schema([
                                $isPro
                                ? Forms\Components\Repeater::make('modifiers')
                                    ->label('Grupos de Opciones')
                                    ->relationship('modifierGroups')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')->label('Nombre (ej. Tallas)')->required(),
                                        Forms\Components\TextInput::make('min_selections')->numeric()->default(0)->label('Mín'),
                                        Forms\Components\TextInput::make('max_selections')->numeric()->default(1)->label('Máx'),
                                    ])
                                    ->collapsed()
                                : Forms\Components\Placeholder::make('upgrade_promo')
                                    ->label('Opciones Avanzadas')
                                    ->content(new \Illuminate\Support\HtmlString('
                                        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700">
                                            <p class="font-bold">Disponible en Plan Pro</p>
                                            <p>Actualiza para agregar tallas, colores y extras a tus productos.</p>
                                        </div>
                                    ')),
                            ]),
                    ])->columnSpan(2),

                // 2. SIDEBAR COLUMN (RIGHT - 1/3)
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Detalles y Precio')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Precio (Lps)')
                                    ->numeric()
                                    ->prefix('L.')
                                    ->required(),

                                Forms\Components\Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->label('Categoría')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('menu_section_id')
                                    ->relationship('menuSection', 'name')
                                    ->label('Sección del Menú')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Toggle::make('is_visible')
                                    ->label('Visible al Público')
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),
                            ]),
                    ])->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('product-images')
                    ->label('Imagen')
                    ->collection('product-images')
                    ->conversion('thumb')
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->limit(30)
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('HNL')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name'),

                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visibilidad'),
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

    use \App\Filament\Traits\ScopesToTenant;

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
