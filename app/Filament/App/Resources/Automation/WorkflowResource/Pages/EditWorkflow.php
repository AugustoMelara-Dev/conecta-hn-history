<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Automation\WorkflowResource\Pages;

use App\Filament\App\Resources\Automation\WorkflowResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflow extends EditRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
