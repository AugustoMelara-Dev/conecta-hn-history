<?php

namespace App\Filament\App\Resources\CampaignResource\Pages;

use App\Filament\App\Resources\CampaignResource;
use Filament\Resources\Pages\ListRecords;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
