<?php

declare(strict_types=1);

namespace App\Filament\Resources\FieldDefinitionResource\Pages;

use App\Filament\Resources\FieldDefinitionResource;
use App\Filament\Resources\Pages\ListAdminRecords;
use Filament\Actions\CreateAction;

class ListFieldDefinitions extends ListAdminRecords
{
    protected static string $resource = FieldDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

