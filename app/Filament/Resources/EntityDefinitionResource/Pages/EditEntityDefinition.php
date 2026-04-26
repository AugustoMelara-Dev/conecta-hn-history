<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntityDefinitionResource\Pages;

use App\Filament\Resources\EntityDefinitionResource;
use App\Filament\Resources\Pages\EditAdminRecord;

class EditEntityDefinition extends EditAdminRecord
{
    protected static string $resource = EntityDefinitionResource::class;
}

