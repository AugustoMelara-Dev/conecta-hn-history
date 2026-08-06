<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\TaskResource\Pages;

use App\Filament\App\Resources\Crm\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Nueva tarea')]; }
}
