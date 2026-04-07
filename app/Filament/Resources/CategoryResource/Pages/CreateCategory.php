<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Pages\CreateAdminRecord;

class CreateCategory extends CreateAdminRecord
{
    protected static string $resource = CategoryResource::class;
}
