<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SettingResource\Pages;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\LaravelSettings\Models\SettingsProperty;

class SettingResource extends Resource
{
    protected static ?string $model = SettingsProperty::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $modelLabel = 'setting';

    protected static ?string $pluralModelLabel = 'Settings';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->components([
                Tabs\Tab::make('General')->components([
                    TextInput::make('app_name')->label('Nombre de la app'),
                    TextInput::make('support_email')->label('Email de soporte')->email(),
                ]),
                Tabs\Tab::make('Features')->components([
                    Toggle::make('feature_ai_assistants')->label('AI Assistants')->default(true),
                    Toggle::make('feature_automation')->label('Automation')->default(true),
                    Toggle::make('feature_import_export')->label('Import/Export')->default(true),
                ]),
                Tabs\Tab::make('Limits')->components([
                    TextInput::make('max_tenants')->label('Máx tenants')->numeric(),
                    TextInput::make('max_ai_calls_per_month')->label('Máx AI calls/mes')->numeric(),
                ]),
                Tabs\Tab::make('Advanced')->components([
                    Textarea::make('raw_config')->label('Raw config (JSON)')->rows(5),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Setting'),
                TextColumn::make('group')->label('Grupo'),
            ])
            ->actions([
                Action::make('save')
                    ->label('Guardar')
                    ->action(fn () => Notification::make()->title('Settings guardados')->success()->send()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSettings::route('/')];
    }
}
