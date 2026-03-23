<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuSectionResource\Pages;

use App\Filament\App\Resources\Pages\CreateTenantRecord;
use App\Filament\App\Resources\MenuSectionResource;

class CreateMenuSection extends CreateTenantRecord
{
    protected static string $resource = MenuSectionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
