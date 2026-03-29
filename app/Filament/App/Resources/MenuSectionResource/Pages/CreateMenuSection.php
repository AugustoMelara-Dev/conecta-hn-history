<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuSectionResource\Pages;

use App\Filament\App\Resources\MenuSectionResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMenuSection extends CreateTenantRecord
{
    protected static string $resource = MenuSectionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return static::getResource()::createOwnedMenuSection($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
