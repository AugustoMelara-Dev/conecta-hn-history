<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\CompanyResource\Pages;

use App\Filament\App\Resources\Crm\CompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva empresa')];
    }
}
