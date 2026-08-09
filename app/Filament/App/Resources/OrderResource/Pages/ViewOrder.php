<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\OrderResource\Pages;

use App\Filament\App\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('whatsapp')
                ->label('Contactar cliente')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->url(fn (): string => 'https://wa.me/'.preg_replace('/[^0-9]/', '', (string) $this->record->customer_phone))
                ->openUrlInNewTab(),
            Actions\Action::make('print')
                ->label('Imprimir ticket')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('orders.print-ticket', ['order' => $this->record->getKey()]))
                ->openUrlInNewTab(),
            Actions\Action::make('receipt')
                ->label('Descargar recibo')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('receipt.download', ['order' => $this->record->getKey()])),
        ];
    }

    public function getTitle(): string
    {
        return 'Pedido '.$this->record->order_number;
    }
}
