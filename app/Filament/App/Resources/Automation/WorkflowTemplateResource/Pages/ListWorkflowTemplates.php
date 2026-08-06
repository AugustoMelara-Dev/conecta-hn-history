<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowTemplateResource\Pages;

use App\Filament\App\Resources\Automation\WorkflowTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowTemplates extends ListRecords
{
    protected static string $resource = WorkflowTemplateResource::class;
}
