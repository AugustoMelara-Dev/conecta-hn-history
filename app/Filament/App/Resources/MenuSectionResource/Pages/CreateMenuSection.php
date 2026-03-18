<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuSectionResource\Pages;

use App\Filament\App\Resources\MenuSectionResource;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuSection extends CreateRecord
{
    protected static string $resource = MenuSectionResource::class;

    /**
     * Automáticamente asignar tenant_id al crear
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        $data['tenant_id'] = $tenant instanceof Tenant ? (string) $tenant->getKey() : null;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
