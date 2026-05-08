<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\TenantUserResource\Pages;

use App\Filament\App\Resources\TenantUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantUsers extends ListRecords
{
    protected static string $resource = TenantUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Agregar miembro')
                ->icon('heroicon-o-user-plus'),
        ];
    }

    public function getTitle(): string
    {
        return 'Equipo';
    }

    public function getSubheading(): string
    {
        return 'Gestiona quien puede acceder a tu negocio y con que permisos.';
    }
}
