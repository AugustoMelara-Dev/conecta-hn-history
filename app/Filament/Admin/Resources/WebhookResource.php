<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WebhookResource\Pages;
use App\Models\Webhook;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cloud';
    protected static string | \UnitEnum | null $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'Webhooks';
    protected static ?string $modelLabel = 'webhook';
    protected static ?string $pluralModelLabel = 'Webhooks';
    protected static ?int $navigationSort = 31;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Endpoint')->components([
                Grid::make(2)->components([
                    TextInput::make('name')->label('Nombre')->required(),
                    TextInput::make('url')->label('URL')->url()->required(),
                    Select::make('events')->label('Eventos')->multiple()->options([
                        'order.created' => 'Order created',
                        'deal.won' => 'Deal won',
                        'contact.created' => 'Contact created',
                    ]),
                    Select::make('http_method')->label('Método')->options(['GET' => 'GET', 'POST' => 'POST'])->default('POST'),
                    Toggle::make('is_active')->label('Activo')->default(true),
                ]),
                Textarea::make('headers')->label('Headers (JSON)')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre'),
                TextColumn::make('url')->label('URL')->limit(50),
                IconColumn::make('is_active')->label('Activo')->boolean(),
                TextColumn::make('last_triggered_at')->label('Último trigger')->dateTime('d/m/Y H:i')->placeholder('Nunca'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('replay')
                    ->label('Replay')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn () => null),
                DeleteAction::make(),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWebhooks::route('/'), 'create' => Pages\CreateWebhook::route('/create'), 'edit' => Pages\EditWebhook::route('/{record}/edit')];
    }
}
