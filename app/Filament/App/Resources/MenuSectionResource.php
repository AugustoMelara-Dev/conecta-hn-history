<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Catalog\Commands\CreateMenuSectionCommand;
use App\Application\Catalog\Commands\DeleteMenuSectionCommand;
use App\Application\Catalog\Commands\ReorderMenuSectionsCommand;
use App\Application\Catalog\Commands\UpdateMenuSectionCommand;
use App\Application\Catalog\Handlers\CreateMenuSectionHandler;
use App\Application\Catalog\Handlers\DeleteMenuSectionHandler;
use App\Application\Catalog\Handlers\ReorderMenuSectionsHandler;
use App\Application\Catalog\Handlers\UpdateMenuSectionHandler;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Filament\App\Resources\MenuSectionResource\Pages;
use App\Models\MenuSection;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class MenuSectionResource extends Resource
{
    protected static ?string $model = MenuSection::class;

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Secciones de menú';
    }

    public static function getModelLabel(): string
    {
        return 'Sección';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Secciones de menú';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catálogo';
    }

    public static function canViewAny(): bool
    {
        $tenant = static::getTenantRecord();

        return $tenant instanceof Tenant
            && $tenant->business_type !== BusinessType::SERVICE
            && Gate::allows('viewAny', MenuSection::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $count = MenuSection::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Nueva sección')
                    ->description('Crea categorías para organizar tu menú.')
                    ->icon('heroicon-o-folder-plus')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la sección')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Ej: Platos fuertes, bebidas, postres')
                            ->autofocus()
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Visible en mi menú')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Desactiva para ocultar temporalmente la sección.'),
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
                    ->color(fn (int|string|null $state): string => (int) $state > 0 ? 'success' : 'danger')
                    ->icon(fn (int|string|null $state): string => (int) $state > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->tooltip(fn (int|string|null $state): string => (int) $state > 0
                        ? "Esta sección tiene {$state} productos"
                        : 'Sección vacía - Agrega productos'),
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
                    ->updateStateUsing(function (MenuSection $record, mixed $state): bool {
                        $updatedRecord = static::updateOwnedMenuSection($record, [
                            'is_active' => (bool) $state,
                        ]);

                        return (bool) $updatedRecord->is_active;
                    })
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
                EditAction::make()
                    ->iconButton()
                    ->using(fn (MenuSection $record, array $data): MenuSection => static::updateOwnedMenuSection($record, $data)),
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('¿Eliminar esta sección?')
                    ->modalDescription('Los productos dentro de esta sección no se eliminarán.')
                    ->action(function (MenuSection $record): void {
                        static::deleteOwnedMenuSection($record);
                    })
                    ->modalSubmitActionLabel('Sí, eliminar sección')
                    ->successNotificationTitle('Sección eliminada'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('¿Eliminar secciones seleccionadas?')
                        ->modalDescription('Los productos dentro de estas secciones no se eliminarán.')
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (mixed $record): bool => $record instanceof MenuSection)
                                ->each(fn (MenuSection $record) => static::deleteOwnedMenuSection($record));
                        }),
                ]),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavía no tienes secciones creadas',
                'description' => 'Crea secciones para organizar tu catálogo y hacerlo más claro para el cliente.',
                'buttonLabel' => 'Crear primera sección',
                'buttonUrl' => static::getUrl('create'),
            ]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuSections::route('/'),
            'create' => Pages\CreateMenuSection::route('/create'),
            'edit' => Pages\EditMenuSection::route('/{record}/edit'),
        ];
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createOwnedMenuSection(array $data): MenuSection
    {
        return app(CreateMenuSectionHandler::class)->handle(
            new CreateMenuSectionCommand(
                tenantId: static::resolveTenantIdOrFail(),
                name: (string) ($data['name'] ?? ''),
                isActive: (bool) ($data['is_active'] ?? true),
            )
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function updateOwnedMenuSection(MenuSection $record, array $data): MenuSection
    {
        return app(UpdateMenuSectionHandler::class)->handle(
            new UpdateMenuSectionCommand(
                tenantId: static::resolveTenantIdOrFail(),
                menuSectionId: (int) $record->getKey(),
                name: array_key_exists('name', $data) ? (string) $data['name'] : null,
                isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            )
        );
    }

    public static function deleteOwnedMenuSection(MenuSection $record): void
    {
        app(DeleteMenuSectionHandler::class)->handle(
            new DeleteMenuSectionCommand(
                tenantId: static::resolveTenantIdOrFail(),
                menuSectionId: (int) $record->getKey(),
            )
        );
    }

    /**
     * @param  array<int|string>  $order
     */
    public static function reorderOwnedMenuSections(array $order): void
    {
        app(ReorderMenuSectionsHandler::class)->handle(
            new ReorderMenuSectionsCommand(
                tenantId: static::resolveTenantIdOrFail(),
                orderedMenuSectionIds: static::normalizeMenuSectionIds($order),
            )
        );
    }

    protected static function resolveTenantIdOrFail(): string
    {
        $tenant = static::getTenantRecord();

        if (! $tenant instanceof Tenant) {
            throw new \RuntimeException('Unable to resolve tenant context for menu section mutation.');
        }

        return (string) $tenant->getKey();
    }

    /**
     * @param  array<int|string>  $order
     * @return array<int, int>
     */
    protected static function normalizeMenuSectionIds(array $order): array
    {
        $normalized = [];

        foreach ($order as $menuSectionId) {
            $menuSectionId = (int) $menuSectionId;

            if ($menuSectionId <= 0 || in_array($menuSectionId, $normalized, true)) {
                continue;
            }

            $normalized[] = $menuSectionId;
        }

        return $normalized;
    }
}
