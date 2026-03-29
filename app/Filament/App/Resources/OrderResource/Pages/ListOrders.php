<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\OrderResource\Pages;

use App\Filament\App\Resources\OrderResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListOrders extends ListTenantRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Centro de Pedidos';
    }

    public function getSubheading(): string
    {
        return 'Gestiona los pedidos de tus clientes en tiempo real';
    }
}
