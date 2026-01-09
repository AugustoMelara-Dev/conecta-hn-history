<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantProfileResource\Pages;

use App\Filament\App\Resources\TenantProfileResource;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
