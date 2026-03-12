<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;

use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Quick status transitions
            Actions\Action::make('confirm')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'pending')
                ->action(fn () => $this->record->transitionTo('confirmed')),

            Actions\Action::make('process')
                ->label('En Preparación')
                ->icon('heroicon-o-fire')
                ->color('warning')
                ->visible(fn () => in_array($this->record->status, ['pending', 'confirmed']))
                ->action(fn () => $this->record->transitionTo('processing')),

            Actions\Action::make('ready')
                ->label('Listo')
                ->icon('heroicon-o-gift')
                ->color('success')
                ->visible(fn () => $this->record->status === 'processing')
                ->action(fn () => $this->record->transitionTo('ready')),

            Actions\Action::make('deliver')
                ->label('Entregado')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'ready')
                ->action(fn () => $this->record->transitionTo('delivered')),

            Actions\Action::make('whatsapp')
                ->label('WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn () => $this->record->getWhatsAppUrl())
                ->openUrlInNewTab(),

            Actions\EditAction::make(),
        ];
    }

    public function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                \Section::make('Información del Pedido')
                    ->columns(3)
                    ->schema([
                        \TextEntry::make('order_number')
                            ->label('Número de Orden')
                            ->size('lg')
                            ->weight('bold')
                            ->color('primary'),

                        \TextEntry::make('status')
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

                        \TextEntry::make('created_at')
                            ->label('Recibido')
                            ->since(),
                    ]),

                \Section::make('Cliente')
                    ->columns(2)
                    ->schema([
                        \TextEntry::make('customer_name')
                            ->label('Nombre'),
                        \TextEntry::make('customer_phone')
                            ->label('Teléfono')
                            ->url(fn ($state) => "tel:{$state}"),
                        \TextEntry::make('customer_email')
                            ->label('Email')
                            ->url(fn ($state) => $state ? "mailto:{$state}" : null),
                        \TextEntry::make('delivery_type')
                            ->label('Tipo de Entrega')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state === 'delivery' ? 'Domicilio' : 'Recoger'),
                        \TextEntry::make('customer_address')
                            ->label('Dirección')
                            ->columnSpanFull(),
                        \TextEntry::make('customer_notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),

                \Section::make('Productos')
                    ->schema([
                        \RepeatableEntry::make('items')
                            ->schema([
                                \TextEntry::make('quantity')
                                    ->label('Cant.')
                                    ->weight('bold'),
                                \TextEntry::make('product_name')
                                    ->label('Producto'),
                                \TextEntry::make('options_description')
                                    ->label('Opciones')
                                    ->color('gray'),
                                \TextEntry::make('line_total')
                                    ->label('Total')
                                    ->formatStateUsing(fn ($state) => 'L. '.number_format($state, 2))
                                    ->weight('bold'),
                            ])
                            ->columns(4),
                    ]),

                \Section::make('Totales')
                    ->columns(4)
                    ->schema([
                        \TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state) => 'L. '.number_format($state, 2)),
                        \TextEntry::make('delivery_fee')
                            ->label('Envío')
                            ->formatStateUsing(fn ($state) => 'L. '.number_format($state, 2)),
                        \TextEntry::make('discount_amount')
                            ->label('Descuento')
                            ->formatStateUsing(fn ($state) => '-L. '.number_format($state, 2)),
                        \TextEntry::make('total')
                            ->label('Total')
                            ->formatStateUsing(fn ($state) => 'L. '.number_format($state, 2))
                            ->size('lg')
                            ->weight('bold')
                            ->color('success'),
                    ]),
            ]);
    }
}
