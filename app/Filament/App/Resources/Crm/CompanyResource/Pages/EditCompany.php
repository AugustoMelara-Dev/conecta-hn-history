<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\CompanyResource\Pages;

use App\Filament\App\Resources\Crm\CompanyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
