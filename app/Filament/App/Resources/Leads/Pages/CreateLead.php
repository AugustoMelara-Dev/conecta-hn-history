<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Leads\Pages;

use App\Filament\App\Resources\Pages\CreateTenantRecord;
use App\Filament\App\Resources\Leads\LeadResource;

class CreateLead extends CreateTenantRecord
{
    protected static string $resource = LeadResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
