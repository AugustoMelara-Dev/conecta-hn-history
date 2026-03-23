<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\LocationResource\Pages;

use App\Filament\App\Resources\LocationResource;
use App\Models\Location;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = new Location($data);
        $record->tenant_id = Filament::getTenant()?->getKey();
        $record->save();

        return $record;
    }
}
