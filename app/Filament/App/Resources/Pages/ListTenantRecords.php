<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Pages;

use Filament\Resources\Pages\ListRecords;

abstract class ListTenantRecords extends ListRecords
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
