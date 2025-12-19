<?php

namespace App\Filament\App\Resources\TenantUserResource\Pages;

use App\Filament\App\Resources\TenantUserResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantUser extends CreateRecord
{
    protected static string $resource = TenantUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Filament::getTenant()->id;
        $data['invited_at'] = now();
        $data['invited_by'] = auth()->user()->email;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        return 'Agregar Miembro al Equipo';
    }
}

