<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\FollowerResource\Pages;

use App\Filament\App\Resources\FollowerResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListFollowers extends ListTenantRecords
{
    protected static string $resource = FollowerResource::class;

    public function getSubheading(): ?string
    {
        $plan = FollowerResource::getPlanPresentation();

        if (($plan['hasFollowerInsights'] ?? false) === true) {
            return $plan['followersCopy'] ?? 'Aquí verás la audiencia que ya sigue tu negocio.';
        }

        return $plan['followersCopy'] ?? 'En plan Base mantienes el conteo general. El detalle completo de seguidores se habilita con PRO.';
    }

    protected function getHeaderWidgets(): array
    {
        return parent::getHeaderWidgets();
    }
}
