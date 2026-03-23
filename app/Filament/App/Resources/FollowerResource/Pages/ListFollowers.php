<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\FollowerResource\Pages;

use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Filament\App\Resources\FollowerResource;

class ListFollowers extends ListTenantRecords
{
    protected static string $resource = FollowerResource::class;

    public function getSubheading(): ?string
    {
        $tenant = filament()->getTenant();
        $isPro = $tenant instanceof \App\Models\Tenant ? (bool) $tenant->is_pro : false;

        return $isPro
            ? 'Aqui veras la audiencia que ya sigue tu negocio.'
            : 'En plan Base mantienes el conteo general. El detalle completo de seguidores se habilita con PRO.';
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
