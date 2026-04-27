<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Filament\App\Resources\Dynamic\DynamicRecordResource\Pages;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\DynamicRecord;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Tenant;
use App\Services\DynamicForms\DynamicRuntimeFeatureManager;
use App\Services\DynamicForms\FormCompilerService;
use App\Services\DynamicForms\SchemaRegistryService;
use App\Services\DynamicForms\TemplateRenderService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DynamicRecordResource extends Resource
{
    protected static ?string $model = DynamicRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationLabel(): string
    {
        return 'Registros dinamicos';
    }

    public static function getModelLabel(): string
    {
        return 'Registro dinamico';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Registros dinamicos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operacion';
    }

    public static function getNavigationSort(): ?int
    {
        return 12;
    }

    public static function canViewAny(): bool
    {
        return static::getTenantRecord() instanceof Tenant
            && app(DynamicRuntimeFeatureManager::class)->isRecordRuntimeEnabled();
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
        return static::canViewAny() && $record instanceof DynamicRecord;
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('General')
                ->schema([
                    Select::make('entity_definition_id')
                        ->label('Entidad dinamica')
                        ->options(fn (): array => static::entityOptions())
                        ->searchable()
                        ->live()
                        ->required()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('payload_json', []);
                        })
                        ->disabled(fn (?DynamicRecord $record): bool => $record instanceof DynamicRecord),
                    TextInput::make('status')
                        ->label('Estado')
                        ->default((string) config('dynamic-forms.default_record_status', 'active'))
                        ->maxLength(24),
                ])
                ->columns(2),

            Section::make('Campos dinamicos')
                ->schema(function (Get $get): array {
                    $entityId = (string) ($get('entity_definition_id') ?? '');
                    if ($entityId === '') {
                        return [
                            Placeholder::make('dynamic_schema_placeholder')
                                ->content('Selecciona una entidad para cargar el formulario dinamico.'),
                        ];
                    }

                    $entity = EntityDefinition::query()->find($entityId);
                    if (! $entity instanceof EntityDefinition) {
                        return [
                            Placeholder::make('dynamic_schema_missing')
                                ->content('No se encontro la entidad seleccionada.'),
                        ];
                    }

                    $schemaData = app(SchemaRegistryService::class)->getActiveSchemaData($entity);

                    return app(FormCompilerService::class)->compileFormSchema($schemaData, 'payload_json');
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entityDefinition.name')
                    ->label('Entidad')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Sucursal')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity_definition_id')
                    ->label('Entidad')
                    ->options(fn (): array => static::entityOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'active',
                        'draft' => 'draft',
                        'closed' => 'closed',
                        'archived' => 'archived',
                    ]),
            ])
            ->actions([
                Action::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(function (DynamicRecord $record): ?StreamedResponse {
                        $template = static::resolveDefaultTemplate($record);
                        if (! $template instanceof DynamicPrintTemplate) {
                            Notification::make()
                                ->title('No hay plantilla publicada')
                                ->body('Publica una plantilla para esta entidad y accion default.')
                                ->warning()
                                ->send();

                            return null;
                        }

                        $entity = $record->entityDefinition;
                        if (! $entity instanceof EntityDefinition) {
                            return null;
                        }

                        $schema = app(SchemaRegistryService::class)->getActiveSchema($entity);
                        $payload = is_array($record->payload_json) ? $record->payload_json : [];
                        $payload['id'] = (string) $record->getKey();
                        $payload['status'] = $record->status;
                        $payload['created_at'] = $record->created_at?->toDateTimeString();
                        $payload['updated_at'] = $record->updated_at?->toDateTimeString();

                        $tenant = static::getTenantRecord();
                        $tenantContext = $tenant instanceof Tenant ? $tenant->toArray() : [];
                        $pdf = app(TemplateRenderService::class)->renderPdf(
                            $template,
                            [
                                'tenant' => $tenantContext,
                                'record' => $payload,
                            ],
                            $schema
                        );

                        $filename = sprintf('%s-%s.pdf', $template->template_key, $record->getKey());

                        return response()->streamDownload(function () use ($pdf): void {
                            echo $pdf;
                        }, $filename);
                    }),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDynamicRecords::route('/'),
            'create' => Pages\CreateDynamicRecord::route('/create'),
            'edit' => Pages\EditDynamicRecord::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['entityDefinition', 'owner', 'location']);
    }

    /**
     * @return array<string, string>
     */
    protected static function entityOptions(): array
    {
        $tenant = static::getTenantRecord();
        if (! $tenant instanceof Tenant) {
            return [];
        }

        $allowedEntityKeys = app(RubroManifestReaderInterface::class)->allowedDynamicEntityKeysForTenant($tenant);

        return EntityDefinition::query()
            ->where(function (Builder $query) use ($tenant): void {
                $query->where('tenant_id', $tenant->getKey())
                    ->orWhere(function (Builder $global): void {
                        $global->whereNull('tenant_id')
                            ->where('status', 'published');
                    });
            })
            ->when($allowedEntityKeys !== [], function (Builder $query) use ($allowedEntityKeys, $tenant): void {
                $query->where(function (Builder $scoped) use ($allowedEntityKeys, $tenant): void {
                    $scoped->where('tenant_id', $tenant->getKey())
                        ->orWhereIn('entity_key', $allowedEntityKeys);
                });
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(static fn (EntityDefinition $entity): array => [
                (string) $entity->getKey() => "{$entity->name} ({$entity->entity_key})",
            ])
            ->all();
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    protected static function resolveDefaultTemplate(DynamicRecord $record): ?DynamicPrintTemplate
    {
        return DynamicPrintTemplate::query()
            ->where('status', 'published')
            ->where('action_key', 'default')
            ->where('entity_definition_id', $record->entity_definition_id)
            ->where(function (Builder $query) use ($record): void {
                $query->where('tenant_id', $record->tenant_id)
                    ->orWhereNull('tenant_id');
            })
            ->orderByRaw('tenant_id IS NULL')
            ->first();
    }
}
