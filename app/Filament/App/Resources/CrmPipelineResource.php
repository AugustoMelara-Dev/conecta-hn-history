<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Filament\App\Resources\CrmPipelineResource\Pages;
use App\Filament\App\Resources\CrmPipelineResource\RelationManagers;
use App\Models\CrmPipeline;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CrmPipelineResource extends Resource
{
    protected static ?string $model = CrmPipeline::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public static function getNavigationLabel(): string
    {
        return 'Pipelines';
    }

    public static function getModelLabel(): string
    {
        return 'Pipeline';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pipelines comerciales';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::allows(Permission::CRM_DEALS_VIEW);
    }

    public static function canCreate(): bool
    {
        return static::allows(Permission::CRM_DEALS_UPDATE);
    }

    public static function canEdit($record): bool
    {
        return $record instanceof CrmPipeline
            && static::recordBelongsToCurrentTenant($record)
            && static::allows(Permission::CRM_DEALS_UPDATE);
    }

    public static function canDelete($record): bool
    {
        return $record instanceof CrmPipeline
            && static::recordBelongsToCurrentTenant($record)
            && ! (bool) $record->is_default
            && ! $record->deals()->exists()
            && static::allows(Permission::CRM_DEALS_DELETE);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pipeline comercial')
                    ->description('Configura etapas de seguimiento. Esto no convierte prospectos automaticamente.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('key')
                            ->label('Llave')
                            ->helperText('Identificador interno estable, por ejemplo ventas-retail.')
                            ->maxLength(120),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripcion')
                            ->rows(3)
                            ->maxLength(2000),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_default')
                                    ->label('Pipeline principal')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Activo')
                                    ->default(true),
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Orden')
                                    ->numeric()
                                    ->integer()
                                    ->default(0),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::scopedQuery($query))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Pipeline')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (CrmPipeline $record): string => $record->description ?: 'Seguimiento comercial manual'),
                Tables\Columns\TextColumn::make('key')
                    ->label('Llave')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Principal')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('stages_count')
                    ->label('Etapas')
                    ->counts('stages'),
                Tables\Columns\TextColumn::make('deals_count')
                    ->label('Oportunidades')
                    ->counts('deals'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (CrmPipeline $record): bool => static::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn (CrmPipeline $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([]),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'CRM gated',
                'heading' => 'Configura tu primer pipeline',
                'description' => 'Define etapas para oportunidades comerciales. Los leads y clientes siguen separados hasta vincularlos.',
                'buttonLabel' => static::canCreate() ? 'Crear pipeline' : null,
                'buttonUrl' => static::canCreate() ? static::getUrl('create') : null,
            ]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmPipelines::route('/'),
            'create' => Pages\CreateCrmPipeline::route('/create'),
            'edit' => Pages\EditCrmPipeline::route('/{record}/edit'),
        ];
    }

    public static function currentTenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopedQuery(parent::getEloquentQuery());
    }

    public static function normalizeDefaultPipeline(CrmPipeline $pipeline): void
    {
        if (! (bool) $pipeline->is_default) {
            return;
        }

        CrmPipeline::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $pipeline->tenant_id)
            ->whereKeyNot($pipeline->getKey())
            ->update(['is_default' => false]);
    }

    private static function allows(Permission $permission): bool
    {
        $tenant = static::currentTenant();
        $user = auth()->user();

        if (! $tenant instanceof Tenant || ! $user instanceof User) {
            return false;
        }

        if (! static::tenantHasCrmFeature($tenant)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return app(TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            Capability::CRM_PIPELINE_MANAGEMENT,
            $permission,
        );
    }

    private static function tenantHasCrmFeature(Tenant $tenant): bool
    {
        return $tenant->hasFeature(TenantFeature::CRM_PIPELINES)
            || $tenant->hasFeature(TenantFeature::MARKETING_SUITE);
    }

    private static function recordBelongsToCurrentTenant(CrmPipeline $record): bool
    {
        $tenant = static::currentTenant();

        return $tenant instanceof Tenant && (string) $record->tenant_id === (string) $tenant->getKey();
    }

    private static function scopedQuery(Builder $query): Builder
    {
        $tenant = static::currentTenant();

        if (! $tenant instanceof Tenant) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->withCount(['stages', 'deals']);
    }
}
