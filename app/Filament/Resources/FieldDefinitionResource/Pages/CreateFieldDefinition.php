<?php

declare(strict_types=1);

namespace App\Filament\Resources\FieldDefinitionResource\Pages;

use App\Filament\Resources\FieldDefinitionResource;
use App\Filament\Resources\Pages\CreateAdminRecord;

class CreateFieldDefinition extends CreateAdminRecord
{
    protected static string $resource = FieldDefinitionResource::class;
}

