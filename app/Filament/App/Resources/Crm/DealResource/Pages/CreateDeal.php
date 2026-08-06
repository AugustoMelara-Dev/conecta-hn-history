<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\DealResource\Pages;

use App\Filament\App\Resources\Crm\DealResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeal extends CreateRecord
{
    protected static string $resource = DealResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
        }
        return $data;
    }
}
