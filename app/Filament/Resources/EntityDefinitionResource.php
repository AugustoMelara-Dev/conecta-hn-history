<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EntityDefinitionResource\Pages;
use App\Models\Dynamic\EntityDefinition;
use App\Services\DynamicForms\DynamicRuntimeFeatureManager;
use App\Services\DynamicForms\SchemaRegistryService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class EntityDefinitionResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = EntityDefinition::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    public static function getModelLabel(): string
    {
        return 'Entidad dinamica';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Entidades dinamicas';
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
        return static::canViewAny() && $record instanceof EntityDefinition;
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
            Section::make('Identidad')
                ->schema([
                    Select::make('tenant_id')
                        ->label('Tenant owner')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Vacio = blueprint global de superadmin.'),
                    Select::make('owner_scope')
                        ->label('Owner scope')
                        ->options([
                            'superadmin' => 'superadmin',
                            'tenant' => 'tenant',
                        ])
                        ->default('superadmin')
                        ->required(),
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
                    TextInput::make('business_segment_key')
                        ->label('Segmento')
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
                        ->label('Descripcion')
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
                    ->sortable()
                    ->description(fn (EntityDefinition $record): string => (string) $record->entity_key),
                Tables\Columns\TextColumn::make('module_key')
                    ->label('Modulo')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('Global'),
                Tables\Columns\TextColumn::make('owner_scope')
                    ->label('Scope')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('fields_count')
                    ->label('Campos')
                    ->counts('fields'),
                Tables\Columns\TextColumn::make('activeSchemaVersion.version_number')
                    ->label('Version activa')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'draft',
                        'review' => 'review',
                        'published' => 'published',
                        'archived' => 'archived',
                    ]),
            ])
            ->actions([
                Action::make('publishSchema')
                    ->label('Publicar schema')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
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
            'index' => Pages\ListEntityDefinitions::route('/'),
            'create' => Pages\CreateEntityDefinition::route('/create'),
            'edit' => Pages\EditEntityDefinition::route('/{record}/edit'),
        ];
    }
}
