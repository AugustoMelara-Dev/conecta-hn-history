<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Leads\Pages;

use App\Filament\App\Resources\Leads\LeadResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use Filament\Actions\CreateAction;

class ListLeads extends ListTenantRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    // Tabs removed - getTabs() with Tab class not available in Filament v4
    // Status filtering can be done via table filters instead
}
