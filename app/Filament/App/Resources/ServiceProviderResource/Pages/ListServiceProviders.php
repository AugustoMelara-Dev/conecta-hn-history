<?php

namespace App\Filament\App\Resources\ServiceProviderResource\Pages;

use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Filament\App\Resources\ServiceProviderResource;
use Filament\Actions;

class ListServiceProviders extends ListTenantRecords
{
    protected static string $resource = ServiceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
