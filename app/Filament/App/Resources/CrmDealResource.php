<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use Filament\Schemas\Schema;
use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Crm\CanonicalOpportunityWriter;
use App\Application\Markets\TenantMarketResolver;
use App\Application\TenantGovernance\Crm\CrmDefaultPipelineService;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Filament\App\Resources\CrmDealResource\Pages;
use App\Filament\App\Resources\CrmDealResource\RelationManagers;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;


class CrmDealResource extends Resource
{
    protected static ?string $model = CrmDeal::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    public static function getNavigationLabel(): string
    {
        return 'Oportunidades';
    }

    public static function getModelLabel(): string
    {
        return 'Oportunidad';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Oportunidades comerciales';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
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
        return static::allows(Permission::CRM_DEALS_CREATE);
    }

    public static function canEdit($record): bool
    {
        return $record instanceof CrmDeal
            && static::recordBelongsToCurrentTenant($record)
            && static::allows(Permission::CRM_DEALS_UPDATE);
    }

    public static function canDelete($record): bool
    {
        return $record instanceof CrmDeal
            && static::recordBelongsToCurrentTenant($record)
            && static::allows(Permission::CRM_DEALS_DELETE);
    }

    public static function canDeleteAny(): bool
    {
        return static::allows(Permission::CRM_DEALS_DELETE);
    }

    public static function canMoveStage(): bool
    {
        return static::allows(Permission::CRM_DEALS_MOVE_STAGE);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Oportunidad comercial')
                    ->description('Seguimiento comercial con oportunidades. No reemplaza automaticamente prospectos ni clientes.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Titulo')
                            ->required()
                            ->maxLength(255),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('crm_pipeline_id')
                                    ->label('Pipeline')
                                    ->options(fn (): array => static::pipelineOptions())
                                    ->default(fn (): ?int => static::defaultPipeline()?->getKey())
                                    ->native(false)
                                    ->required(),
                                Forms\Components\Select::make('crm_stage_id')
                                    ->label('Etapa')
                                    ->options(fn (): array => static::stageOptions())
                                    ->default(fn (): ?int => static::defaultStage()?->getKey())
                                    ->native(false)
                                    ->required()
                                    ->disabled(fn (): bool => ! static::canMoveStage())
                                    ->dehydrated(),
                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        CrmDeal::STATUS_OPEN => 'Abierta',
                                        CrmDeal::STATUS_WON => 'Ganada',
                                        CrmDeal::STATUS_LOST => 'Perdida',
                                    ])
                                    ->default(CrmDeal::STATUS_OPEN)
                                    ->native(false)
                                    ->required(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label('Valor')
                                    ->numeric()
                                    ->prefix(fn (): string => static::currencySymbol())
                                    ->default(0),
                                Forms\Components\Select::make('currency')
                                    ->label('Moneda')
                                    ->options(fn (): array => static::currencyOptions())
                                    ->default(fn (): ?string => static::marketCurrency())
                                    ->native(false)
                                    ->required(),
                                Forms\Components\DatePicker::make('expected_close_at')
                                    ->label('Cierre esperado'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('customer_profile_id')
                                    ->label('Cliente/contacto')
                                    ->options(fn (): array => static::customerOptions())
                                    ->searchable()
                                    ->native(false),
                                Forms\Components\Select::make('lead_id')
                                    ->label('Prospecto relacionado')
                                    ->options(fn (): array => static::leadOptions())
                                    ->searchable()
                                    ->native(false),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('owner_user_id')
                                    ->label('Owner')
                                    ->options(fn (): array => static::teamOptions())
                                    ->searchable()
                                    ->native(false),
                                Forms\Components\Select::make('location_id')
                                    ->label('Sede')
                                    ->options(fn (): array => static::locationOptions())
                                    ->searchable()
                                    ->native(false),
                                Forms\Components\TextInput::make('source')
                                    ->label('Origen')
                                    ->placeholder('manual, whatsapp, web'),
                            ]),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas internas')
                            ->rows(4),
                        Forms\Components\TextInput::make('lost_reason')
                            ->label('Razon de perdida')
                            ->maxLength(255)
                            ->visible(fn ($get): bool => $get('status') === CrmDeal::STATUS_LOST),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::scopedQuery($query))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Oportunidad')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (CrmDeal $record): string => static::dealContext($record)),
                Tables\Columns\TextColumn::make('stage.name')
                    ->label('Etapa')
                    ->badge()
                    ->color(fn (CrmDeal $record): string => match ($record->stage?->type) {
                        CrmStage::TYPE_WON => 'success',
                        CrmStage::TYPE_LOST => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(static fn (?string $state): string => match ($state) {
                        CrmDeal::STATUS_WON => 'Ganada',
                        CrmDeal::STATUS_LOST => 'Perdida',
                        default => 'Abierta',
                    })
                    ->color(static fn (?string $state): string => match ($state) {
                        CrmDeal::STATUS_WON => 'success',
                        CrmDeal::STATUS_LOST => 'danger',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->money(fn (CrmDeal $record): string => (string) $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('customerProfile.name')
                    ->label('Cliente')
                    ->default('Sin cliente')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->default('Sin owner')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expected_close_at')
                    ->label('Cierre esperado')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('crm_stage_id')
                    ->label('Etapa')
                    ->options(fn (): array => static::stageOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        CrmDeal::STATUS_OPEN => 'Abierta',
                        CrmDeal::STATUS_WON => 'Ganada',
                        CrmDeal::STATUS_LOST => 'Perdida',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (CrmDeal $record): bool => static::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn (CrmDeal $record): bool => static::canDelete($record))
                    ->action(function (CrmDeal $record): void {
                        $tenant = static::currentTenant();
                        $actor = Filament::auth()->user();
                        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
                            throw new \RuntimeException('CRM deletion requires tenant and actor context.');
                        }
                        app(CanonicalOpportunityWriter::class)->archive(
                            (string) $tenant->getKey(),
                            (string) $actor->getKey(),
                            (int) $record->getKey(),
                        );
                    }),
            ])
            ->emptyState(fn () => view('filament.app.components.table-empty-state', [
                'badge' => 'CRM gated',
                'heading' => 'Todavia no hay oportunidades',
                'description' => 'Usa oportunidades para dar seguimiento comercial. Los prospectos y clientes siguen siendo registros separados hasta que los vincules manualmente.',
                'buttonLabel' => static::canCreate() ? 'Crear oportunidad' : null,
                'buttonUrl' => static::canCreate() ? static::getUrl('create') : null,
            ]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DealNotesRelationManager::class,
            RelationManagers\DealTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmDeals::route('/'),
            'create' => Pages\CreateCrmDeal::route('/create'),
            'edit' => Pages\EditCrmDeal::route('/{record}/edit'),
        ];
    }

    public static function currentTenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public static function marketCurrency(): ?string
    {
        $tenant = static::currentTenant();

        return $tenant instanceof Tenant
            ? app(TenantMarketResolver::class)->resolveOrNull($tenant)?->currencyCode
            : null;
    }

    /**
     * @return array<string, string>
     */
    public static function currencyOptions(): array
    {
        $currency = static::marketCurrency();

        return $currency === null ? [] : [$currency => $currency];
    }

    public static function currencySymbol(): string
    {
        $currency = static::marketCurrency();
        if ($currency === null) {
            return '';
        }

        return (string) (
            DB::table('currencies')
                ->where('code', $currency)
                ->value('symbol') ?: $currency
        );
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopedQuery(parent::getEloquentQuery());
    }

    public static function defaultPipeline(): ?CrmPipeline
    {
        $tenant = static::currentTenant();

        return $tenant instanceof Tenant
            ? app(CrmDefaultPipelineService::class)->ensureForTenant($tenant)
            : null;
    }

    public static function defaultStage(): ?CrmStage
    {
        return static::defaultPipeline()
            ?->stages()
            ->where('is_default', true)
            ->first();
    }

    /**
     * @return array<int, string>
     */
    public static function pipelineOptions(): array
    {
        $tenant = static::currentTenant();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        static::defaultPipeline();

        return CrmPipeline::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function stageOptions(): array
    {
        $tenant = static::currentTenant();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        $pipeline = static::defaultPipeline();

        if (! $pipeline instanceof CrmPipeline) {
            return [];
        }

        return CrmStage::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('crm_pipeline_id', $pipeline->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function customerOptions(): array
    {
        $tenant = static::currentTenant();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return CustomerProfile::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('name')
            ->limit(100)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function leadOptions(): array
    {
        $tenant = static::currentTenant();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return Lead::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('created_at')
            ->limit(100)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function teamOptions(): array
    {
        $tenant = static::currentTenant();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return User::query()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function locationOptions(): array
    {
        $tenant = static::currentTenant();

        if (! $tenant instanceof Tenant || ! Schema::hasTable('locations')) {
            return [];
        }

        return Location::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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

    private static function recordBelongsToCurrentTenant(CrmDeal $record): bool
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
            ->where('tenant_id', $tenant->getKey())
            ->with(['stage', 'customerProfile', 'owner']);
    }

    private static function dealContext(CrmDeal $record): string
    {
        $bits = array_filter([
            $record->pipeline?->name,
            $record->lead?->name !== null ? 'Prospecto: '.$record->lead->name : null,
            $record->source !== null ? 'Origen: '.$record->source : null,
        ]);

        return $bits === [] ? 'Seguimiento comercial manual' : implode(' - ', $bits);
    }
}
