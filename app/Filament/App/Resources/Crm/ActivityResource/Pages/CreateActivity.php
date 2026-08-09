<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ActivityResource\Pages;

use App\Filament\App\Resources\Crm\ActivityResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateActivity extends CreateRecord
{
    protected static string $resource = ActivityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
        }

        return $data;
    }
}
