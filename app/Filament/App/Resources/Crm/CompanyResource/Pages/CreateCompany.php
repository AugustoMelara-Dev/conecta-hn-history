<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\CompanyResource\Pages;

use App\Filament\App\Resources\Crm\CompanyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
        }
        return $data;
    }
}
