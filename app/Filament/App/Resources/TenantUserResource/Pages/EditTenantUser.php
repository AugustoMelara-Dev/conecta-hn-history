<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantUserResource\Pages;

use App\Filament\App\Resources\TenantUserResource;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

/**
 * @property TenantMembership $record
 */
class EditTenantUser extends EditRecord
{
    protected static string $resource = TenantUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => ! $this->record->isOwner()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return $data;
        }

        return TenantUserResource::normalizeMembershipData($tenant, $data);
    }

    public function getTitle(): string
    {
        return 'Editar Miembro: '.($this->record->user->name ?? 'Equipo');
    }
}
