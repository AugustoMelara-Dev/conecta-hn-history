<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\LayoutDefinitionResource\Pages;
use App\Models\Dynamic\LayoutDefinition;
use App\Services\DynamicForms\DynamicRuntimeFeatureManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LayoutDefinitionResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = LayoutDefinition::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public static function getModelLabel(): string
    {
        return 'Layout dinamico';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Layouts dinamicos';
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
        return static::canViewAny() && $record instanceof LayoutDefinition;
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
            Section::make('Layout')
                ->schema([
                    Select::make('entity_definition_id')
                        ->label('Entidad')
                        ->relationship('entityDefinition', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('layout_key')
                        ->label('Layout key')
                        ->options([
                            'form' => 'form',
                            'list' => 'list',
                            'detail' => 'detail',
                        ])
                        ->default('form')
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
                    Textarea::make('layout_json')
                        ->label('Layout JSON')
                        ->rows(14)
                        ->required()
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
                Tables\Columns\TextColumn::make('entityDefinition.name')
                    ->label('Entidad')
                    ->searchable(),
                Tables\Columns\TextColumn::make('layout_key')
                    ->label('Layout key')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since(),
            ])
            ->actions([
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
            'index' => Pages\ListLayoutDefinitions::route('/'),
            'create' => Pages\CreateLayoutDefinition::route('/create'),
            'edit' => Pages\EditLayoutDefinition::route('/{record}/edit'),
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
