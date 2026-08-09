<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowResource\Pages;

use App\Application\Automation\WorkflowDefinitionService;
use App\Filament\App\Resources\Automation\WorkflowResource;
use App\Models\AutomationWorkflowDefinition;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CreateWorkflow extends CreateRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        $actor = auth()->user();
        if ($tenant === null || $actor === null) {
            throw new RuntimeException('Authenticated tenant context is required.');
        }

        $result = app(WorkflowDefinitionService::class)->createDraft(
            (string) $tenant->getKey(),
            (string) $data['workflow_key'],
            json_decode((string) $data['definition_json'], true, flags: JSON_THROW_ON_ERROR),
            (string) $actor->getAuthIdentifier(),
        );

        return AutomationWorkflowDefinition::query()
            ->whereKey($result['definition_id'])
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();
    }
}
