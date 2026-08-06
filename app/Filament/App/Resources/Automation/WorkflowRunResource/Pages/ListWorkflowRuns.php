<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowRunResource\Pages;

use App\Filament\App\Resources\Automation\WorkflowRunResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowRuns extends ListRecords
{
    protected static string $resource = WorkflowRunResource::class;
}
