<?php

declare(strict_types=1);

namespace App\Filament\Resources\LayoutDefinitionResource\Pages;

use App\Filament\Resources\LayoutDefinitionResource;
use App\Filament\Resources\Pages\ListAdminRecords;
use Filament\Actions\CreateAction;

class ListLayoutDefinitions extends ListAdminRecords
{
    protected static string $resource = LayoutDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

