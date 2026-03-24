<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\CustomerTouchpointResource\Pages;

use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\CustomerTouchpointResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;

class ListCustomerTouchpoints extends ListTenantRecords
{
    protected static string $resource = CustomerTouchpointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('customers')
                ->label('Ir a clientes')
                ->icon('heroicon-o-users')
                ->url(function (): string {
                    $tenant = Filament::getTenant();

                    return $tenant instanceof Tenant
                        ? CustomerProfileResource::getUrl('index', ['tenant' => $tenant->slug])
                        : '#';
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'Historial de contactos';
    }

    public function getSubheading(): string
    {
        return 'Bitacora de emails y seguimientos comerciales ejecutados desde el CRM.';
    }
}
