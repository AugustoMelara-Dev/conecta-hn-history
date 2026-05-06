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
                ->label('Contactar Cliente')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->url(fn () => 'https://wa.me/'.preg_replace('/[^0-9]/', '', $this->record->customer_phone))
                ->openUrlInNewTab(),

            Actions\Action::make('print')
                ->label('Imprimir Ticket')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => null), // TODO: Implementar impresión
        ];
    }

    public function getTitle(): string
    {
        return 'Pedido '.$this->record->order_number;
    }
}
