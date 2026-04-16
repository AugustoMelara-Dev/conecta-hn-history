<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuSectionResource\Pages;

use App\Filament\App\Resources\MenuSectionResource;
use App\Filament\App\Resources\Pages\ListTenantRecords;

class ListMenuSections extends ListTenantRecords
{
    protected static string $resource = MenuSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nueva Sección')
                ->icon('heroicon-o-plus')
                ->using(fn (array $data) => static::getResource()::createOwnedMenuSection($data)),
        ];
    }

    /**
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        static::getResource()::reorderOwnedMenuSections($order);
    }
}
