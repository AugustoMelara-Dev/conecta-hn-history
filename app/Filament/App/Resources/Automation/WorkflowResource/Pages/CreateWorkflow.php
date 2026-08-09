<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowResource\Pages;

use App\Filament\App\Resources\Automation\WorkflowResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflow extends CreateRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $data['tenant_id'] = $tenant->getKey();
        }

        return $data;
    }
}
