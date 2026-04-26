<?php

declare(strict_types=1);

namespace App\Filament\Resources\DynamicPrintTemplateResource\Pages;

use App\Filament\Resources\DynamicPrintTemplateResource;
use App\Filament\Resources\Pages\ListAdminRecords;
use Filament\Actions\CreateAction;

class ListDynamicPrintTemplates extends ListAdminRecords
{
    protected static string $resource = DynamicPrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

