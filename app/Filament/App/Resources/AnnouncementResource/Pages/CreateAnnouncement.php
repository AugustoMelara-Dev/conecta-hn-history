<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\AnnouncementResource\Pages;

use App\Filament\App\Resources\AnnouncementResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;

class CreateAnnouncement extends CreateTenantRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
