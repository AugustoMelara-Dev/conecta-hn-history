<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';
    protected static string | \UnitEnum | null $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'API Keys';
    protected static ?string $modelLabel = 'api key';
    protected static ?string $pluralModelLabel = 'API Keys';
    protected static ?int $navigationSort = 32;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('API Key')->components([
                Grid::make(2)->components([
                    TextInput::make('name')->label('Nombre')->required(),
                    Select::make('tenant_id')->label('Tenant')->relationship('tenant', 'name'),
                    Select::make('scopes')->label('Scopes')->multiple()->options(['crm' => 'CRM', 'billing' => 'Billing', 'automation' => 'Automation']),
                    Toggle::make('is_active')->label('Activo')->default(true),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre'),
                TextColumn::make('tenant.name')->label('Tenant'),
                TextColumn::make('scopes')->label('Scopes'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
                TextColumn::make('last_used_at')->label('Último uso')->dateTime('d/m/Y H:i')->placeholder('Nunca'),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListApiKeys::route('/'), 'create' => Pages\CreateApiKey::route('/create'), 'edit' => Pages\EditApiKey::route('/{record}/edit')];
    }
}
