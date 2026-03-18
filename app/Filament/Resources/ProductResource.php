<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationGroup(): ?string
    {
        return 'GestiÃ³n de CatÃ¡logo';
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
        $tenant = self::getTenantRecord();
        $planValue = $tenant instanceof Tenant ? (string) $tenant->getRawOriginal('plan_type') : null;
        $isPro = in_array($planValue, ['pro', 'enterprise'], true);

        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('InformaciÃ³n del Producto')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del Producto')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\RichEditor::make('description')
                                    ->label('DescripciÃ³n')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'bulletList',
                                        'orderedList',
                                        'link',
                                        'redo',
                                        'undo',
                                    ])
                                    ->columnSpan('full'),
                            ]),

                        Section::make('GalerÃ­a de ImÃ¡genes')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('images')
                                    ->label('ImÃ¡genes del Producto')
                                    ->collection('product-images')
                                    ->image()
                                    ->imageEditor()
                                    ->reorderable()
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->maxSize(2048)
                                    ->maxFiles(5)
                                    ->columnSpan('full'),
                            ]),

                        Section::make('Opciones y Modificadores')
                            ->schema([
                                $isPro
                                    ? Forms\Components\Repeater::make('modifiers')
                                        ->label('Grupos de Opciones')
                                        ->relationship('modifierGroups')
                                        ->schema([
                                            Forms\Components\TextInput::make('name')->label('Nombre (ej. Tallas)')->required(),
                                            Forms\Components\TextInput::make('min_selections')->numeric()->default(0)->label('MÃ­n'),
                                            Forms\Components\TextInput::make('max_selections')->numeric()->default(1)->label('MÃ¡x'),
                                        ])
                                        ->collapsed()
                                    : Forms\Components\Placeholder::make('upgrade_promo')
                                        ->label('Opciones Avanzadas')
                                        ->content(new HtmlString('
                                            <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700">
                                                <p class="font-bold">Disponible en Plan Pro</p>
                                                <p>Actualiza para agregar tallas, colores y extras a tus productos.</p>
                                            </div>
                                        ')),
                            ]),
                    ])
                    ->columnSpan(2),

                Group::make()
                    ->schema([
                        Section::make('Detalles y Precio')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Precio (Lps)')
                                    ->numeric()
                                    ->prefix('L.')
                                    ->required(),

                                Forms\Components\Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->label('CategorÃ­a')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('menu_section_id')
                                    ->relationship('menuSection', 'name')
                                    ->label('SecciÃ³n del MenÃº')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Toggle::make('is_visible')
                                    ->label('Visible al PÃºblico')
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('product-images')
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
                    ->label('CategorÃ­a')
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
                    ->label('CategorÃ­a')
                    ->relationship('category', 'name'),

                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visibilidad'),
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

    use \App\Filament\Traits\ScopesToTenant;

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    private static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
