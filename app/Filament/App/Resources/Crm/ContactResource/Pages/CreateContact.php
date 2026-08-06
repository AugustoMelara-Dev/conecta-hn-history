<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ContactResource\Pages;

use App\Filament\App\Resources\Crm\ContactResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContact extends CreateRecord
{
    protected static string $resource = ContactResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
        }
        return $data;
    }
}
