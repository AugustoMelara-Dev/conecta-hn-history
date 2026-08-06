<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowTemplateResource\Pages;

use App\Filament\App\Resources\Automation\WorkflowTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowTemplate extends EditRecord
{
    protected static string $resource = WorkflowTemplateResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
