<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

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
use Illuminate\Support\Facades\Gate;

class MenuSectionResource extends Resource
{
    protected static ?string $model = MenuSection::class;

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Secciones de Menu';
    }

    public static function getModelLabel(): string
    {
        return 'Seccion';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Secciones de Menu';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalogo';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', MenuSection::class);
    }

    public static function shouldRegisterNavigation(): bool
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
                Section::make('Nueva seccion')
                    ->description('Crea categorias para organizar tu menu.')
                    ->icon('heroicon-o-folder-plus')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la seccion')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Ej: Platos fuertes, bebidas, postres')
                            ->autofocus()
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('sort_order')
                            ->default(function (): int {
                                $tenant = static::getTenantRecord();

                                if (! $tenant instanceof Tenant) {
                                    return 1;
                                }

                                return ((int) MenuSection::query()
                                    ->where('tenant_id', (string) $tenant->getKey())
                                    ->max('sort_order')) + 1;
                            }),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Visible en mi menu')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Desactiva para ocultar temporalmente la seccion.'),
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
                        ? "Esta seccion tiene {$state} productos"
                        : 'Seccion vacia - Agrega productos'),
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
                    ->tooltip('Activar o desactivar seccion'),
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
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->modalHeading('Eliminar esta seccion?')
                    ->modalDescription('Los productos dentro de esta seccion no se eliminaran.')
                    ->modalSubmitActionLabel('Si, eliminar seccion')
                    ->successNotificationTitle('Seccion eliminada'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Eliminar secciones seleccionadas?')
                        ->modalDescription('Los productos dentro de estas secciones no se eliminaran.'),
                ]),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'heading' => 'Todavia no tienes secciones creadas',
                'description' => 'Crea secciones para organizar tu catalogo y hacerlo mas claro para el cliente.',
                'buttonLabel' => 'Crear primera seccion',
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
}
