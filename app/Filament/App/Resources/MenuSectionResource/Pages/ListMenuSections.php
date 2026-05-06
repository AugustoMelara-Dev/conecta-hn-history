<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuSectionResource\Pages;

use App\Filament\App\Resources\MenuSectionResource;
use Filament\Resources\Pages\ListRecords;

class ListMenuSections extends ListRecords
{
    protected static string $resource = MenuSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nueva Sección')
                ->icon('heroicon-o-plus'),
        ];
    }
}
