<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\Pages\ListAdminRecords;
use App\Filament\Resources\CategoryResource;
use Filament\Actions;

class ListCategories extends ListAdminRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
