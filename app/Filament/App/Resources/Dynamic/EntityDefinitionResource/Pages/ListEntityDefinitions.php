<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic\EntityDefinitionResource\Pages;

use App\Filament\App\Resources\Dynamic\EntityDefinitionResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use Filament\Actions\CreateAction;

class ListEntityDefinitions extends ListTenantRecords
{
    protected static string $resource = EntityDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

