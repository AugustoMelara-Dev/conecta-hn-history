<?php

namespace App\Filament\App\Resources\LoyaltyRewardResource\Pages;

use App\Filament\App\Resources\LoyaltyRewardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyRewards extends ListRecords
{
    protected static string $resource = LoyaltyRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
