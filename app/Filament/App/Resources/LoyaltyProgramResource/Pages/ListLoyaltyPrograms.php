<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\LoyaltyProgramResource\Pages;

use App\Filament\App\Resources\LoyaltyProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyPrograms extends ListRecords
{
    protected static string $resource = LoyaltyProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
