<?php

namespace App\Filament\App\Resources\ServiceResource\Pages;

use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Filament\App\Resources\ServiceResource;
use Filament\Actions;

class ListServices extends ListTenantRecords
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
