<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerProfileResource\RelationManagers;

use App\Filament\App\Resources\OrderResource;
use App\Models\Order;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Historial de pedidos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Pedido')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'Pendiente',
                        Order::STATUS_CONFIRMED => 'Confirmado',
                        Order::STATUS_PROCESSING => 'En curso',
                        Order::STATUS_READY => 'Listo',
                        Order::STATUS_DELIVERED => 'Entregado',
                        Order::STATUS_CANCELLED => 'Cancelado',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('HNL'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (Order $record): string {
                        $tenant = Filament::getTenant();

                        return $tenant instanceof Tenant
                            ? OrderResource::getUrl('view', ['tenant' => $tenant->slug, 'record' => $record])
                            : '#';
                    }),
            ])
            ->bulkActions([]);
    }
}
