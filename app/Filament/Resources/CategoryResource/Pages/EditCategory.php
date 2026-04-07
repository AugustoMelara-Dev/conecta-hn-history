<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Pages\EditAdminRecord;

class EditCategory extends EditAdminRecord
{
    protected static string $resource = CategoryResource::class;
}
