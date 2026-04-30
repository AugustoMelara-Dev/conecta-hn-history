<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Filament\App\Resources\Dynamic\EntityDefinitionResource\Pages;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Tenant;
use App\Services\DynamicForms\DynamicRuntimeFeatureManager;
use App\Services\DynamicForms\EntityDefinitionClonerService;
use App\Services\DynamicForms\SchemaRegistryService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EntityDefinitionResource extends Resource
{
    protected static ?string $model = EntityDefinition::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    public static function getNavigationLabel(): string
    {
        return 'Formularios dinamicos';
    }

    public static function getModelLabel(): string
    {
        return 'Formulario dinamico';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Formularios dinamicos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuración';
    }

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function canViewAny(): bool
    {
        return static::getTenantRecord() instanceof Tenant
            && app(DynamicRuntimeFeatureManager::class)->isTenantConfiguratorEnabled();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        if (! $record instanceof EntityDefinition) {
            return false;
        }

        return static::canViewAny()
            && (string) $record->tenant_id === (string) static::getTenantRecord()?->getKey();
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Definicion')
                ->schema([
                    TextInput::make('module_key')
                        ->label('Modulo')
                        ->required()
                        ->maxLength(80),
                    TextInput::make('entity_key')
                        ->label('Entity key')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    Select::make('business_segment_key')
                        ->label('Segmento')
                        ->options(fn (): array => app(RubroManifestReaderInterface::class)->rubroLabels())
                        ->searchable()
                        ->placeholder('Todos los rubros')
                        ->maxLength(120),
                    Select::make('status')
                        ->label('Estado')
                        ->options([
                            'draft' => 'draft',
                            'review' => 'review',
                            'published' => 'published',
                            'archived' => 'archived',
                        ])
                        ->default('draft')
                        ->required(),
                    Toggle::make('allow_tenant_overrides')
                        ->label('Permite override tenant')
                        ->default(true),
                    Toggle::make('is_locked')
                        ->label('Bloqueado')
                        ->default(false),
                    Textarea::make('description')
                        ->label('Descripción')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->description(fn (EntityDefinition $record): string => (string) $record->entity_key),
                Tables\Columns\TextColumn::make('module_key')
                    ->label('Modulo')
                    ->badge(),
                Tables\Columns\TextColumn::make('tenant_id')
                    ->label('Origen')
                    ->formatStateUsing(fn ($state): string => $state ? 'Tenant' : 'Blueprint')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('activeSchemaVersion.version_number')
                    ->label('Version')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since(),
            ])
            ->actions([
                Action::make('clone')
                    ->label('Clonar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn (EntityDefinition $record): bool => $record->tenant_id === null)
                    ->requiresConfirmation()
                    ->action(function (EntityDefinition $record): void {
                        $tenant = static::getTenantRecord();
                        if (! $tenant instanceof Tenant) {
                            return;
                        }

                        try {
                            $cloned = app(EntityDefinitionClonerService::class)
                                ->cloneForTenant($record, (string) $tenant->getKey(), auth()->id() ? (string) auth()->id() : null);

                            Notification::make()
                                ->title('Blueprint clonado')
                                ->body("Formulario {$cloned->name} listo para personalizar.")
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('No se pudo clonar')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('publishSchema')
                    ->label('Publicar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (EntityDefinition $record): bool => static::canEdit($record))
                    ->requiresConfirmation()
                    ->action(function (EntityDefinition $record): void {
                        $version = app(SchemaRegistryService::class)->publish(
                            $record,
                            auth()->user()
                        );

                        Notification::make()
                            ->title('Schema publicado')
                            ->body("Version {$version->version_number} publicada.")
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn (EntityDefinition $record): bool => static::canEdit($record)),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntityDefinitions::route('/'),
            'create' => Pages\CreateEntityDefinition::route('/create'),
            'edit' => Pages\EditEntityDefinition::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = static::getTenantRecord();

        $query = parent::getEloquentQuery();

        if (! $tenant instanceof Tenant) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($tenant): void {
            $inner->where('tenant_id', $tenant->getKey())
                ->orWhere(function (Builder $global): void {
                    $global->whereNull('tenant_id')
                        ->where('status', 'published');
                });
        });
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
