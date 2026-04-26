<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntityDefinitionResource\Pages;

use App\Filament\Resources\EntityDefinitionResource;
use App\Filament\Resources\Pages\ListAdminRecords;
use Filament\Actions\CreateAction;

class ListEntityDefinitions extends ListAdminRecords
{
    protected static string $resource = EntityDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

