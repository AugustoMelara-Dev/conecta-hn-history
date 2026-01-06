<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantProfileResource\Pages;

use App\Filament\App\Resources\TenantProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
