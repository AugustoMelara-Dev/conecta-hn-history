<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ServiceResource\Pages;

use App\Filament\App\Resources\ServiceResource;
use App\Filament\App\Resources\Pages\CreateTenantRecord;

class CreateService extends CreateTenantRecord
{
    protected static string $resource = ServiceResource::class;
}
