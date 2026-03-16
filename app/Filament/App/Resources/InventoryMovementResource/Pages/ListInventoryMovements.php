<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InventoryMovementResource\Pages;

use App\Filament\App\Resources\InventoryMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
