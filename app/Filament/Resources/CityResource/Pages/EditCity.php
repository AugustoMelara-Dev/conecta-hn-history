<?php

declare(strict_types=1);

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Resources\Pages\EditAdminRecord;
use App\Filament\Resources\CityResource;

class EditCity extends EditAdminRecord
{
    protected static string $resource = CityResource::class;
}
