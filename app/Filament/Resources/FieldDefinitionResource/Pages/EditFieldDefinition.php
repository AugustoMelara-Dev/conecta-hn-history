<?php

declare(strict_types=1);

namespace App\Filament\Resources\FieldDefinitionResource\Pages;

use App\Filament\Resources\FieldDefinitionResource;
use App\Filament\Resources\Pages\EditAdminRecord;

class EditFieldDefinition extends EditAdminRecord
{
    protected static string $resource = FieldDefinitionResource::class;
}

