<?php

namespace App\Filament\App\Resources\LoyaltyRewardResource\Pages;

use App\Filament\App\Resources\LoyaltyRewardResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use Filament\Actions;

class ListLoyaltyRewards extends ListTenantRecords
{
    protected static string $resource = LoyaltyRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
