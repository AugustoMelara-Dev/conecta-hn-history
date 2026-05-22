<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\LoyaltyProgramResource\Pages;

use App\Filament\App\Resources\LoyaltyProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyProgram extends EditRecord
{
    protected static string $resource = LoyaltyProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
