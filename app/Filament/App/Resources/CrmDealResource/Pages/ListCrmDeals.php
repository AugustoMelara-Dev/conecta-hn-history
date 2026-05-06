<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CrmDealResource\Pages;

use App\Filament\App\Resources\CrmDealResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmDeals extends ListRecords
{
    protected static string $resource = CrmDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => CrmDealResource::canCreate()),
        ];
    }
}
