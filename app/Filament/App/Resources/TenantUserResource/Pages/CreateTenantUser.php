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
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : null;
        $normalizedData = $tenant instanceof Tenant
            ? TenantUserResource::normalizeMembershipData($tenant, $data)
            : $data;

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $data['user_id'])
            ->first();

        if (! $membership instanceof TenantMembership) {
            $membership = new TenantMembership([
                'user_id' => $data['user_id'],
                'role' => $normalizedData['role'],
                'preset_key' => $normalizedData['preset_key'] ?? null,
                'granted_permissions' => $normalizedData['granted_permissions'] ?? [],
                'accepted_at' => now()->toDateTimeString(),
            ]);
            $membership->tenant_id = $tenantId;
        } else {
            $membership->role = $normalizedData['role'];
            $membership->preset_key = $normalizedData['preset_key'] ?? null;
            $membership->granted_permissions = $normalizedData['granted_permissions'] ?? [];
            $membership->accepted_at = now()->toDateTimeString();
        }

        $membership->save();

        return $membership;
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
