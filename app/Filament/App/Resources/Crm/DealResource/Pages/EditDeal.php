<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\DealResource\Pages;

use App\Filament\App\Resources\Crm\DealResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeal extends EditRecord
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
