<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantUserResource\Pages;

use App\Filament\App\Resources\TenantUserResource;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenantUser extends CreateRecord
{
    protected static string $resource = TenantUserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();

        return TenantMembership::query()->updateOrCreate(
            [
                'tenant_id' => $tenant instanceof Tenant ? (string) $tenant->getKey() : null,
                'user_id' => $data['user_id'],
            ],
            [
                'role' => $data['role'],
                'accepted_at' => now(),
            ]
        );
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
