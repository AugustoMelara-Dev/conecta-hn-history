<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\LoyaltyRewardResource\Pages;

use App\Filament\App\Resources\LoyaltyRewardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyReward extends EditRecord
{
    protected static string $resource = LoyaltyRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
