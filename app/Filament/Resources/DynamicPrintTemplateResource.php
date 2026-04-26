<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicPrintTemplateResource\Pages;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Services\DynamicForms\DynamicRuntimeFeatureManager;
use App\Services\DynamicForms\TemplateVersioningService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class DynamicPrintTemplateResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = DynamicPrintTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    public static function getModelLabel(): string
    {
        return 'Plantilla dinamica';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Plantillas dinamicas';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuracion';
    }

    public static function canViewAny(): bool
    {
        return (auth()->user()?->isSuperAdmin() ?? false) && app(DynamicRuntimeFeatureManager::class)->isAdminConfiguratorEnabled();
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
        return static::canViewAny() && $record instanceof DynamicPrintTemplate;
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Plantilla')
                ->schema([
                    Select::make('tenant_id')
                        ->label('Tenant owner')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Vacio = plantilla global.'),
                    Select::make('entity_definition_id')
                        ->label('Entidad')
                        ->relationship('entityDefinition', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Select::make('owner_scope')
                        ->label('Owner scope')
                        ->options([
                            'superadmin' => 'superadmin',
                            'tenant' => 'tenant',
                        ])
                        ->default('superadmin')
                        ->required(),
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
                        ->placeholder('record.custom_path'),
                    Textarea::make('description')
                        ->label('Descripcion')
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
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('Global'),
                Tables\Columns\TextColumn::make('entityDefinition.name')
                    ->label('Entidad')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('activeVersion.version_number')
                    ->label('Version activa')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since(),
            ])
            ->actions([
                Action::make('publish')
                    ->label('Publicar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (DynamicPrintTemplate $record): void {
                        $version = app(TemplateVersioningService::class)->publish($record, auth()->user());

                        Notification::make()
                            ->title('Plantilla publicada')
                            ->body("Version {$version->version_number} publicada.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
}
