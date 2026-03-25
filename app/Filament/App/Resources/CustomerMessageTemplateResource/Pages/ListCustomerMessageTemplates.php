<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerMessageTemplateResource\Pages;

use App\Filament\App\Resources\CustomerMessageTemplateResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use Filament\Actions\CreateAction;

class ListCustomerMessageTemplates extends ListTenantRecords
{
    protected static string $resource = CustomerMessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
