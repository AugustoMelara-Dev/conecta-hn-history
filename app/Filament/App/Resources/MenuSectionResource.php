<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\MenuSectionResource\Pages;
use App\Models\MenuSection;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * MenuSectionResource - Velvet Rope Feature (PRO-Only)
 *
 * Permite a usuarios PRO organizar su catálogo en secciones:
 * - Entradas, Bebidas, Postres, etc.
 *
 * ESTRATEGIA "VELVET ROPE":
 * - Usuarios Free no pueden ver ni acceder a este módulo
 * - Implementado en canViewAny() y shouldRegisterNavigation()
 */
class MenuSectionResource extends Resource
{
    protected static ?string $model = MenuSection::class;

    protected static ?int $navigationSort = 2;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Secciones de Menú';
    }

    public static function getModelLabel(): string
    {
        return 'Sección';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Secciones de Menú';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catálogo';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VELVET ROPE: Política de Bloqueo PRO-Only
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Solo usuarios PRO pueden ver este recurso
     */
    public static function canViewAny(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant?->is_pro ?? false;
    }

    /**
     * Ocultar del menú de navegación para usuarios Free
     */
    public static function shouldRegisterNavigation(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant?->is_pro ?? false;
    }

    /**
     * Badge de navegación mostrando cantidad
     */
    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return null;
        }

        $count = MenuSection::where('tenant_id', $tenant->id)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FORMULARIO MINIMALISTA (CERO FRICCIÓN)
    // ═══════════════════════════════════════════════════════════════════════
    // Objetivo: Crear una sección en menos de 10 segundos
    // - Solo el nombre es visible y requerido
    // - sort_order se calcula automáticamente (último + 1)
    // - is_active por defecto encendido
    // ═══════════════════════════════════════════════════════════════════════

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Section::make('Nueva Sección')
                    ->description('Crea categorías para organizar tu menú: Entradas, Bebidas, Postres...')
                    ->icon('heroicon-o-folder-plus')
                    ->schema([
                        // ═══ ÚNICO CAMPO VISIBLE ═══
                        Forms\Components\TextInput::make('name')
                            ->label('¿Cómo se llama esta sección?')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Ej: Platos Fuertes, Bebidas, Postres')
                            ->autofocus()
                            ->columnSpanFull(),

                        // ═══ CAMPOS AUTOMÁTICOS (OCULTOS) ═══
                        Forms\Components\Hidden::make('sort_order')
                            ->default(fn () => MenuSection::where('tenant_id', Filament::getTenant()?->id)->max('sort_order') + 1),

                        // ═══ TOGGLE SIMPLE (DEFAULTS TRUE) ═══
                        Forms\Components\Toggle::make('is_active')
                            ->label('Visible en mi menú')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Desactívala para ocultar temporalmente esta sección y sus productos'),
                    ]),
            ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TABLA
    // ═══════════════════════════════════════════════════════════════════════

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

                // ═══════════════════════════════════════════════════════════
                // FEEDBACK DE INVENTARIO: Conteo con colores condicionales
                // ═══════════════════════════════════════════════════════════
                // Rojo = Sección vacía (alerta de acción)
                // Verde = Sección con productos (saludable)
                // ═══════════════════════════════════════════════════════════
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'danger')
                    ->icon(fn ($state): string => $state > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->tooltip(fn ($state): string => $state > 0
                        ? "Esta sección tiene {$state} productos"
                        : '⚠️ Sección vacía - Agrega productos'),

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
                    ->tooltip('Activar/Desactivar sección'),

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

                // ═══════════════════════════════════════════════════════════
                // BORRADO SEGURO: Modal que elimina ansiedad operativa
                // ═══════════════════════════════════════════════════════════
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('¿Eliminar esta sección?')
                    ->modalDescription('⚠️ Los productos dentro de esta sección NO se eliminarán. Simplemente quedarán sin categoría asignada y podrás reorganizarlos después.')
                    ->modalSubmitActionLabel('Sí, eliminar sección')
                    ->successNotificationTitle('Sección eliminada'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading('¿Eliminar secciones seleccionadas?')
                        ->modalDescription('Los productos dentro de estas secciones NO se eliminarán.'),
                ]),
            ])
            ->emptyStateHeading('No tienes secciones creadas')
            ->emptyStateDescription('Crea secciones para organizar tu catálogo como un profesional: Entradas, Bebidas, Postres, etc.')
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
