<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

abstract class CreateAdminRecord extends CreateRecord
{
    public function getTitle(): string
    {
        return 'Crear '.Str::lower($this->getResource()::getModelLabel());
    }

    public function getBreadcrumb(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }
}
