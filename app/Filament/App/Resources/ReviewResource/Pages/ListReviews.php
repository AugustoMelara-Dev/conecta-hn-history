<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ReviewResource\Pages;

use App\Filament\App\Resources\Pages\ListTenantRecords;
use App\Filament\App\Resources\ReviewResource;

class ListReviews extends ListTenantRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
