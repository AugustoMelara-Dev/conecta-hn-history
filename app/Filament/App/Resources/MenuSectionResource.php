<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\MenuSectionResource\Pages;
use App\Models\MenuSection;
use App\Support\Product\TenantAccessMatrix;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuSectionResource extends Resource
{
    protected static ?string $model = MenuSection::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Secciones del menú';
    }

    public static function getModelLabel(): string
    {
        return 'sección';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Secciones del menú';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catálogo';
    }

    public static function canViewAny(): bool
    {
        return static::tenantCanUseMenuSections();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::tenantCanUseMenuSections();
    }

    protected static function tenantCanUseMenuSections(): bool
    {
        return TenantAccessMatrix::currentTenantCanAccess(TenantAccessMatrix::MODULE_MENU_SECTIONS);
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return null;
        }

        $count = MenuSection::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nueva sección')
                    ->description('Organiza tu menú por categorías como entradas, bebidas, platos fuertes o postres.')
                    ->icon('heroicon-o-folder-plus')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la sección')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Ej: Platos fuertes, Bebidas, Postres')
                            ->helperText('Usa un nombre corto y claro para que tus clientes encuentren rápido lo que buscan.')
                            ->autofocus()
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('sort_order')
                            ->default(fn () => MenuSection::where('tenant_id', Filament::getTenant()?->id)->max('sort_order') + 1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Visible en mi menú')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Desactiva esta opción para ocultar temporalmente la sección y sus productos.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-folder'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'danger')
                    ->icon(fn ($state): string => $state > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->tooltip(fn ($state): string => $state > 0
                        ? "Esta sección tiene {$state} productos"
                        : 'Sección vacía. Agrega productos para publicarla mejor.'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activa')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip('Activar o desactivar sección'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton(),

                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('Eliminar esta sección')
                    ->modalDescription('Los productos dentro de esta sección no se eliminarán. Solo quedarán sin sección asignada para que puedas reorganizarlos después.')
                    ->modalSubmitActionLabel('Eliminar sección')
                    ->successNotificationTitle('Sección eliminada'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading('Eliminar secciones seleccionadas')
                        ->modalDescription('Los productos dentro de estas secciones no se eliminarán. Solo quedarán sin sección asignada.'),
                ]),
            ])
            ->emptyStateHeading('No tienes secciones creadas')
            ->emptyStateDescription('Crea secciones para organizar tu catálogo: entradas, bebidas, platos fuertes, postres y más.')
            ->emptyStateIcon('heroicon-o-folder-plus')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear primera sección')
                    ->icon('heroicon-o-plus'),
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
            'index' => Pages\ListMenuSections::route('/'),
            'create' => Pages\CreateMenuSection::route('/create'),
            'edit' => Pages\EditMenuSection::route('/{record}/edit'),
        ];
    }
}



