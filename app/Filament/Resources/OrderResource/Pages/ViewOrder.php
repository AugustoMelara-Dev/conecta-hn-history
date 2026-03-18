<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @extends ViewRecord<Order>
 *
 * @property Order $record
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getRecord(): Order
    {
        return parent::getRecord();
    }

    protected function getHeaderActions(): array
    {
        return [
            // Quick status transitions
            Actions\Action::make('confirm')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === 'pending')
                ->action(fn (): bool => $this->getRecord()->transitionTo('confirmed')),

            Actions\Action::make('process')
                ->label('En Preparación')
                ->icon('heroicon-o-fire')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->getRecord()->status, ['pending', 'confirmed'], true))
                ->action(fn (): bool => $this->getRecord()->transitionTo('processing')),

            Actions\Action::make('ready')
                ->label('Listo')
                ->icon('heroicon-o-gift')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === 'processing')
                ->action(fn (): bool => $this->getRecord()->transitionTo('ready')),

            Actions\Action::make('deliver')
                ->label('Entregado')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === 'ready')
                ->action(fn (): bool => $this->getRecord()->transitionTo('delivered')),

            Actions\Action::make('whatsapp')
                ->label('WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn (): ?string => $this->getRecord()->getWhatsAppUrl())
                ->openUrlInNewTab(),

            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Información del Pedido')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Número de Orden')
                            ->size('lg')
                            ->weight('bold')
                            ->color('primary'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'pending' => 'warning',
                                'confirmed' => 'info',
                                'processing' => 'primary',
                                'ready' => 'success',
                                'delivered' => 'gray',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => Order::KANBAN_STATUSES[$state] ?? ucfirst($state)),

                        TextEntry::make('created_at')
                            ->label('Recibido')
                            ->since(),
                    ]),

                Section::make('Cliente')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Nombre'),
                        TextEntry::make('customer_phone')
                            ->label('Teléfono')
                            ->url(fn ($state) => "tel:{$state}"),
                        TextEntry::make('customer_email')
                            ->label('Email')
                            ->url(fn ($state) => $state ? "mailto:{$state}" : null),
                        TextEntry::make('delivery_type')
                            ->label('Tipo de Entrega')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state === 'delivery' ? 'Domicilio' : 'Recoger'),
                        TextEntry::make('customer_address')
                            ->label('Dirección')
                            ->columnSpanFull(),
                        TextEntry::make('customer_notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),

                Section::make('Productos')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('Cant.')
                                    ->weight('bold'),
                                TextEntry::make('product_name')
                                    ->label('Producto'),
                                TextEntry::make('options_description')
                                    ->label('Opciones')
                                    ->color('gray'),
                                TextEntry::make('line_total')
                                    ->label('Total')
                                    ->formatStateUsing(fn ($state) => 'L. '.number_format($state, 2))
                                    ->weight('bold'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Totales')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state) => 'L. '.number_format($state, 2)),
                        TextEntry::make('delivery_fee')
                            ->label('Envío')
                            ->formatStateUsing(fn ($state) => 'L. '.number_format($state, 2)),
                        TextEntry::make('discount_amount')
                            ->label('Descuento')
                            ->formatStateUsing(fn ($state) => '-L. '.number_format($state, 2)),
                        TextEntry::make('total')
                            ->label('Total')
                            ->formatStateUsing(fn ($state) => 'L. '.number_format($state, 2))
                            ->size('lg')
                            ->weight('bold')
                            ->color('success'),
                    ]),
            ]);
    }
}
