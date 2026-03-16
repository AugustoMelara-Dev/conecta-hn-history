<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\LocationResource\Pages;

use App\Filament\App\Resources\LocationResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
