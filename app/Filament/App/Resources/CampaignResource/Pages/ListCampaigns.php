<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Filament\App\Resources\CampaignResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListCampaigns extends ListTenantRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
