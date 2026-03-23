<?php

namespace App\Filament\App\Resources\ServiceResource\Pages;

use App\Filament\App\Resources\Pages\CreateTenantRecord;
use App\Filament\App\Resources\ServiceResource;

class CreateService extends CreateTenantRecord
{
    protected static string $resource = ServiceResource::class;
}
