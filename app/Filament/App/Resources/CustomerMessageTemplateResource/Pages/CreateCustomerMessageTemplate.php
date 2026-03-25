<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerMessageTemplateResource\Pages;

use App\Filament\App\Resources\CustomerMessageTemplateResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;

class CreateCustomerMessageTemplate extends CreateTenantRecord
{
    protected static string $resource = CustomerMessageTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
