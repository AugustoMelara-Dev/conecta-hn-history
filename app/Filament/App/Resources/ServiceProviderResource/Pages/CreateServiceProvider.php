<?php

namespace App\Filament\App\Resources\ServiceProviderResource\Pages;

use App\Filament\App\Resources\Pages\CreateTenantRecord;
use App\Filament\App\Resources\ServiceProviderResource;

class CreateServiceProvider extends CreateTenantRecord
{
    protected static string $resource = ServiceProviderResource::class;
}
