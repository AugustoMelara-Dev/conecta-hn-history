<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Crm\ContactResource\Pages;

use App\Filament\App\Resources\Crm\ContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo contacto')
                ->icon('heroicon-o-plus'),
        ];
    }
}
