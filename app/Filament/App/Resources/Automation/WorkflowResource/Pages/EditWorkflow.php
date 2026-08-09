<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowResource\Pages;

use App\Application\Automation\WorkflowDefinitionService;
use App\Filament\App\Resources\Automation\WorkflowResource;
use App\Models\AutomationWorkflowDefinition;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EditWorkflow extends EditRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var AutomationWorkflowDefinition $record */
        $record = $this->getRecord();
        $draft = $record->currentDraftVersion;
        $data['definition_json'] = json_encode(
            $draft?->definition ?? ['steps' => []],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $tenant = Filament::getTenant();
        $actor = auth()->user();
        if ($tenant === null || $actor === null || (string) $record->getAttribute('tenant_id') !== (string) $tenant->getKey()) {
            throw new RuntimeException('Authenticated tenant context is required.');
        }

        /** @var AutomationWorkflowDefinition $record */
        $draft = $record->currentDraftVersion;
        $definition = json_decode((string) $data['definition_json'], true, flags: JSON_THROW_ON_ERROR);
        $service = app(WorkflowDefinitionService::class);

        if ($draft === null) {
            $service->createDraft(
                (string) $tenant->getKey(),
                (string) $record->workflow_key,
                $definition,
                (string) $actor->getAuthIdentifier(),
            );
        } else {
            $service->updateDraftDefinition((string) $tenant->getKey(), (string) $draft->getKey(), $definition);
        }

        return $record->refresh();
    }
}
