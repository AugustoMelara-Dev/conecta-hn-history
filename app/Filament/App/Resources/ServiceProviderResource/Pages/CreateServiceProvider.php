<?php

namespace App\Filament\App\Resources\ServiceProviderResource\Pages;

use App\Filament\App\Resources\ServiceProviderResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;

class CreateServiceProvider extends CreateTenantRecord
{
    protected static string $resource = ServiceProviderResource::class;
}
