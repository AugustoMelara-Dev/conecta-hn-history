<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantUserResource\Pages;

use App\Filament\App\Resources\TenantUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantUser extends EditRecord
{
    protected static string $resource = TenantUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Eliminar acceso')
                ->modalHeading('Eliminar miembro del equipo')
                ->modalDescription('Esta accion quitara el acceso de esta persona al negocio. La cuenta de usuario no se elimina.')
                ->modalSubmitActionLabel('Eliminar acceso')
                ->visible(fn () => (string) ($this->record->role->value ?? $this->record->role) !== 'owner'),
        ];
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
