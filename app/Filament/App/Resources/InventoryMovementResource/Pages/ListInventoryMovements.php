<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InventoryMovementResource\Pages;

use App\Filament\App\Resources\InventoryMovementResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListInventoryMovements extends ListTenantRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
