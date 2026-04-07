<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Leads\Pages;

use App\Filament\App\Resources\Leads\LeadResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;

class CreateLead extends CreateTenantRecord
{
    protected static string $resource = LeadResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
