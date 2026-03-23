<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;

abstract class ListAdminRecords extends ListRecords
{
    public function getTitle(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }

    public function getBreadcrumb(): string
    {
        return $this->getResource()::getPluralModelLabel();
    }
}
