<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic;

use App\Filament\App\Resources\Dynamic\DynamicPrintTemplateResource\Pages;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Tenant;
use App\Services\DynamicForms\DynamicRuntimeFeatureManager;
use App\Services\DynamicForms\PrintTemplateClonerService;
use App\Services\DynamicForms\TemplateVersioningService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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

class DynamicPrintTemplateResource extends Resource
{
    protected static ?string $model = DynamicPrintTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    public static function getNavigationLabel(): string
    {
        return 'Plantillas impresas';
    }

    public static function getModelLabel(): string
    {
        return 'Plantilla impresa';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Plantillas impresas';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuración';
    }

    public static function getNavigationSort(): ?int
    {
        return 36;
    }

    public static function canViewAny(): bool
    {
        $features = app(DynamicRuntimeFeatureManager::class);

        return static::getTenantRecord() instanceof Tenant
            && $features->isTenantConfiguratorEnabled()
            && $features->isPrintRuntimeEnabled();
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
        if (! $record instanceof DynamicPrintTemplate) {
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
            Section::make('Plantilla')
                ->schema([
                    Select::make('entity_definition_id')
                        ->label('Entidad')
                        ->relationship('entityDefinition', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    TextInput::make('template_key')
                        ->label('Template key')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('action_key')
                        ->label('Action key')
                        ->required()
                        ->default('default')
                        ->maxLength(120),
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
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
                    Toggle::make('is_locked')
                        ->label('Bloqueado')
                        ->default(false),
                    TagsInput::make('placeholder_whitelist')
                        ->label('Whitelist placeholders')
                        ->placeholder('record.custom_field'),
                    Textarea::make('description')
                        ->label('Descripción')
                        ->rows(3)
                        ->columnSpanFull(),
                    Textarea::make('blocks_json')
                        ->label('Bloques JSON')
                        ->required()
                        ->rows(16)
                        ->formatStateUsing(fn ($state): string => self::toPrettyJson($state))
                        ->dehydrateStateUsing(fn (?string $state): array => self::decodeJson($state))
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
                    ->description(fn (DynamicPrintTemplate $record): string => (string) $record->template_key),
                Tables\Columns\TextColumn::make('action_key')
                    ->label('Accion')
                    ->badge(),
                Tables\Columns\TextColumn::make('tenant_id')
                    ->label('Origen')
                    ->formatStateUsing(fn ($state): string => $state ? 'Tenant' : 'Blueprint')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('activeVersion.version_number')
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
                    ->visible(fn (DynamicPrintTemplate $record): bool => $record->tenant_id === null)
                    ->requiresConfirmation()
                    ->action(function (DynamicPrintTemplate $record): void {
                        $tenant = static::getTenantRecord();
                        if (! $tenant instanceof Tenant) {
                            return;
                        }

                        try {
                            app(PrintTemplateClonerService::class)->cloneForTenant(
                                $record,
                                (string) $tenant->getKey(),
                                auth()->id()
                            );

                            Notification::make()
                                ->title('Plantilla clonada')
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
                Action::make('publish')
                    ->label('Publicar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DynamicPrintTemplate $record): bool => static::canEdit($record))
                    ->requiresConfirmation()
                    ->action(function (DynamicPrintTemplate $record): void {
                        $version = app(TemplateVersioningService::class)->publish($record, auth()->user());

                        Notification::make()
                            ->title('Plantilla publicada')
                            ->body("Version {$version->version_number} publicada.")
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn (DynamicPrintTemplate $record): bool => static::canEdit($record)),
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
            'index' => Pages\ListDynamicPrintTemplates::route('/'),
            'create' => Pages\CreateDynamicPrintTemplate::route('/create'),
            'edit' => Pages\EditDynamicPrintTemplate::route('/{record}/edit'),
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

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(?string $state): array
    {
        if (! is_string($state) || trim($state) === '') {
            return [];
        }

        $decoded = json_decode($state, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function toPrettyJson(mixed $state): string
    {
        if (! is_array($state)) {
            return '';
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '';
    }

    protected static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
