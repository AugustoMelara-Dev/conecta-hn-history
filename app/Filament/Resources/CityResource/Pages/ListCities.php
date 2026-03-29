<?php

declare(strict_types=1);

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Resources\CityResource;
use App\Filament\Resources\Pages\ListAdminRecords;
use Filament\Actions;

class ListCities extends ListAdminRecords
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
