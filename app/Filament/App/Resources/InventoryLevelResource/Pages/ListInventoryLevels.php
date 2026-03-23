<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InventoryLevelResource\Pages;

use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Filament\App\Resources\InventoryLevelResource;

class ListInventoryLevels extends ListTenantRecords
{
    protected static string $resource = InventoryLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
