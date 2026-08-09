<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\TagResource\Pages;

use App\Filament\App\Resources\Crm\TagResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
        }

        return $data;
    }
}
