<?php

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
                ->visible(fn () => $this->record->role !== 'owner'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        return 'Editar Miembro: '.$this->record->user->name;
    }
}
