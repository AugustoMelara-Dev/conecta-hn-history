<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Dynamic\DynamicPrintTemplateResource\Pages;

use App\Filament\App\Resources\Dynamic\DynamicPrintTemplateResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use Filament\Actions\CreateAction;

class ListDynamicPrintTemplates extends ListTenantRecords
{
    protected static string $resource = DynamicPrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

