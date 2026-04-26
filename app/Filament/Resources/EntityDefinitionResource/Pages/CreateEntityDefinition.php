<?php

declare(strict_types=1);

namespace App\Filament\Resources\EntityDefinitionResource\Pages;

use App\Filament\Resources\EntityDefinitionResource;
use App\Filament\Resources\Pages\CreateAdminRecord;

class CreateEntityDefinition extends CreateAdminRecord
{
    protected static string $resource = EntityDefinitionResource::class;
}

