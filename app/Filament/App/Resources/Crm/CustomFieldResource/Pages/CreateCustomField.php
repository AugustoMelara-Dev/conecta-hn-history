<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\CustomFieldResource\Pages;

use App\Filament\App\Resources\Crm\CustomFieldResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomField extends CreateRecord
{
    protected static string $resource = CustomFieldResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
        }
        return $data;
    }
}
