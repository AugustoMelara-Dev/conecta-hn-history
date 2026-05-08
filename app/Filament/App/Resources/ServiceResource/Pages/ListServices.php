<?php

namespace App\Filament\App\Resources\ServiceResource\Pages;

use App\Filament\App\Resources\ServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    protected static string $view = 'filament-panels::resources.pages.list-records';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear servicio'),
        ];
    }
}
