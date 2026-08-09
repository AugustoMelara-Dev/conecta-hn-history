<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowTemplateResource\Pages;

use App\Filament\App\Resources\Automation\WorkflowTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowTemplate extends CreateRecord
{
    protected static string $resource = WorkflowTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = null; // Templates globales

        return $data;
    }
}
