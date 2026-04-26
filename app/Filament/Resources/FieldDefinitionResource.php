<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FieldDefinitionResource\Pages;
use App\Models\Dynamic\FieldDefinition;
use App\Services\DynamicForms\DynamicRuntimeFeatureManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class FieldDefinitionResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = FieldDefinition::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    public static function getModelLabel(): string
    {
        return 'Campo dinamico';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Campos dinamicos';
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
        return static::canViewAny() && $record instanceof FieldDefinition;
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
            Section::make('Campo')
                ->schema([
                    Select::make('entity_definition_id')
                        ->label('Entidad')
                        ->relationship('entityDefinition', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('field_key')
                        ->label('Field key')
                        ->required()
                        ->maxLength(120),
                    Select::make('field_type')
                        ->label('Tipo')
                        ->options(self::fieldTypeOptions())
                        ->required(),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('placeholder')
                        ->label('Placeholder')
                        ->maxLength(255),
                    TextInput::make('help_text')
                        ->label('Ayuda')
                        ->maxLength(500),
                    TextInput::make('sort_order')
                        ->label('Orden')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    Select::make('storage_mode')
                        ->label('Storage mode')
                        ->options([
                            'json' => 'json',
                            'indexed' => 'indexed',
                            'core' => 'core',
                        ])
                        ->default('json')
                        ->required(),
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
                    Toggle::make('is_required')
                        ->label('Requerido')
                        ->default(false),
                    Toggle::make('is_indexed')
                        ->label('Indexado')
                        ->default(false),
                    Toggle::make('is_locked')
                        ->label('Bloqueado')
                        ->default(false),
                    TagsInput::make('validation_rules')
                        ->label('Reglas de validacion')
                        ->placeholder('max:100'),
                    Forms\Components\KeyValue::make('options_json')
                        ->label('Opciones')
                        ->helperText('Solo aplica para select/multiselect. Formato: valor => etiqueta.'),
                    Textarea::make('conditions_json')
                        ->label('Condiciones JSON')
                        ->rows(7)
                        ->formatStateUsing(fn ($state): string => self::toPrettyJson($state))
                        ->dehydrateStateUsing(fn (?string $state): array => self::decodeJson($state)),
                    Textarea::make('visibility_json')
                        ->label('Visibilidad JSON')
                        ->rows(5)
                        ->formatStateUsing(fn ($state): string => self::toPrettyJson($state))
                        ->dehydrateStateUsing(fn (?string $state): array => self::decodeJson($state)),
                    Textarea::make('hooks_json')
                        ->label('Hooks JSON')
                        ->rows(5)
                        ->formatStateUsing(fn ($state): string => self::toPrettyJson($state))
                        ->dehydrateStateUsing(fn (?string $state): array => self::decodeJson($state)),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entityDefinition.name')
                    ->label('Entidad')
                    ->searchable(),
                Tables\Columns\TextColumn::make('field_key')
                    ->label('Field key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('field_type')
                    ->label('Tipo')
                    ->badge(),
                Tables\Columns\TextColumn::make('storage_mode')
                    ->label('Storage')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Req')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_indexed')
                    ->label('Idx')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('field_type')
                    ->options(self::fieldTypeOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'draft',
                        'review' => 'review',
                        'published' => 'published',
                        'archived' => 'archived',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('entity_definition_id')
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFieldDefinitions::route('/'),
            'create' => Pages\CreateFieldDefinition::route('/create'),
            'edit' => Pages\EditFieldDefinition::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function fieldTypeOptions(): array
    {
        /** @var array<int, string> $types */
        $types = config('dynamic-forms.field_types', []);

        $options = [];
        foreach ($types as $type) {
            $options[$type] = $type;
        }

        return $options;
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
