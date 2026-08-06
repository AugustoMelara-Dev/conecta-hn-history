<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\DealResource\Pages;

use App\Filament\App\Resources\Crm\DealResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeals extends ListRecords
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo deal'),
        ];
    }
}
