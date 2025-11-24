<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Notifications\Notification;
use BackedEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-shopping-bag';
    }

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
        return $schema
            ->components([
                Forms\Components\Section::make('Información del Producto')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Producto')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('price')
                            ->label('Precio (L)')
                            ->numeric()
                            ->prefix('L')
                            ->required(),

                        Forms\Components\Select::make('tenant_id')
                            ->relationship('tenant', 'name')
                            ->label('Negocio')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->user()->tenants->first()?->id),
                    ])->columns(2),

                Forms\Components\Section::make('Imagen y Visibilidad')
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Imagen del Producto')
                            ->image()
                            ->directory('products')
                            ->imageEditor(),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible al Público')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Imagen')
                    ->circular(),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Negocio')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('HNL')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->filters([
                //
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->before(function (Tables\Actions\CreateAction $action) {
                        $user = auth()->user();
                        $tenant = $user->tenants->first();
                        
                        if ($tenant && !$tenant->is_pro && $tenant->products()->count() >= 10) {
                            Notification::make()
                                ->danger()
                                ->title('Límite del Plan Gratuito alcanzado')
                                ->body('Has alcanzado el límite de 10 productos. Actualiza a PRO para ilimitados.')
                                ->send();
                            
                            $action->halt();
                        }
                    }),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
