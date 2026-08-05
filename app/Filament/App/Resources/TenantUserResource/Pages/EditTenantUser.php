<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantUserResource\Pages;

use App\Application\Identity\TenantMembershipService;
use App\Filament\App\Resources\TenantUserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditTenantUser extends EditRecord
{
    protected static string $resource = TenantUserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(TenantMembershipService::class)->update(
            membershipId: (string) $record->getKey(),
            actorUserId: (string) auth()->id(),
            role: (string) $data['role'],
            presetKey: filled($data['preset_key'] ?? null) ? (string) $data['preset_key'] : null,
            grantedPermissions: array_values((array) ($data['granted_permissions'] ?? [])),
        );

        return $record->refresh();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        return 'Editar miembro: '.$this->record->user->name;
    }
}
