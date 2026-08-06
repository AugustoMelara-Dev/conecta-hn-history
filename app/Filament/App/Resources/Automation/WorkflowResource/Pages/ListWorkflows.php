<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowResource\Pages;

use App\Filament\App\Resources\Automation\WorkflowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflows extends ListRecords
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo workflow')];
    }
}
