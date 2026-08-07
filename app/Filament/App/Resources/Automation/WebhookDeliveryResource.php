<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation;

use App\Filament\App\Resources\Automation\WebhookDeliveryResource\Pages;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * WebhookDeliveryResource - ReadOnly; retries, success rate.
 */
class WebhookDeliveryResource extends Resource
{
    protected static ?string $model = \App\Models\WebhookDelivery::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cloud';

    protected static string | \UnitEnum | null $navigationGroup = 'Automatización';

    protected static ?string $navigationLabel = 'Webhooks';

    protected static ?string $modelLabel = 'delivery';

    protected static ?string $pluralModelLabel = 'Deliveries';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('webhook.name')->label('Webhook')->searchable(),
                TextColumn::make('event')->label('Evento')->badge(),
                TextColumn::make('status_code')->label('HTTP')->numeric(),
                TextColumn::make('attempts')->label('Intentos')->numeric(),
                TextColumn::make('delivered_at')->label('Entregado')->dateTime('d/m/Y H:i')->placeholder('Pendiente'),
                TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where('tenant_id', $tenant->getKey());
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhookDeliveries::route('/'),
        ];
    }
}
