<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\InventoryLevelResource\Pages;

use App\Filament\App\Resources\InventoryLevelResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryLevels extends ListRecords
{
    protected static string $resource = InventoryLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
