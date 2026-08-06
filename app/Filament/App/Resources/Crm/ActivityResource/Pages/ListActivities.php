<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ActivityResource\Pages;

use App\Filament\App\Resources\Crm\ActivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva actividad')];
    }
}
