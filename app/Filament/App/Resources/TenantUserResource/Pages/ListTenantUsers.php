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
                ->label('Invitar Miembro')
                ->icon('heroicon-o-user-plus'),
        ];
    }

    public function getTitle(): string
    {
        return 'Tu Equipo';
    }

    public function getSubheading(): string
    {
        return 'Gestiona quién puede acceder a tu negocio';
    }
}
