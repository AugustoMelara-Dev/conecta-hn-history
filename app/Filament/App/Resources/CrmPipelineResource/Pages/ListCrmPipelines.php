<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmPipelineResource\Pages;

use App\Filament\App\Resources\CrmPipelineResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use Filament\Actions;

class ListCrmPipelines extends ListTenantRecords
{
    protected static string $resource = CrmPipelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo pipeline')
                ->visible(fn (): bool => CrmPipelineResource::canCreate()),
        ];
    }
}
