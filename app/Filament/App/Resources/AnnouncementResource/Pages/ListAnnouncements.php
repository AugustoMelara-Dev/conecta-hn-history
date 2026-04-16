<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\AnnouncementResource\Pages;

use App\Filament\App\Resources\AnnouncementResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListAnnouncements extends ListTenantRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nuevo Anuncio'),
        ];
    }
}
