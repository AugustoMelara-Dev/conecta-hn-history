<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

abstract class EditAdminRecord extends EditRecord
{
    public function getTitle(): string
    {
        return 'Editar ' . Str::lower($this->getResource()::getModelLabel());
    }

    public function getBreadcrumb(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }
}
