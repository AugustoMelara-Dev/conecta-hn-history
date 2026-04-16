<?php

namespace App\Filament\App\Resources\LoyaltyProgramResource\Pages;

use App\Filament\App\Resources\LoyaltyProgramResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListLoyaltyPrograms extends ListTenantRecords
{
    protected static string $resource = LoyaltyProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
