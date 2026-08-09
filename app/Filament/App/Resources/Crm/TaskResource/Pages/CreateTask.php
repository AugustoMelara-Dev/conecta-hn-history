<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\TaskResource\Pages;

use App\Filament\App\Resources\Crm\TaskResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
            $data['assignee_id'] = $data['assignee_id'] ?? auth()->id();
        }

        return $data;
    }
}
